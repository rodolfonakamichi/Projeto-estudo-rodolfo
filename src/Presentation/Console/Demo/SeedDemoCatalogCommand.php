<?php

declare(strict_types=1);

namespace App\Presentation\Console\Demo;

use App\Application\Attendance\AttendanceQuery;
use App\Application\Attendance\Location\CreateLocation;
use App\Application\Catalog\CatalogQuery;
use App\Application\Catalog\Category\CreateCategory;
use App\Application\Catalog\Product\CreateProduct;
use App\Application\Catalog\Product\ModifierGroupInput;
use App\Application\Catalog\Product\ModifierInput;
use App\Application\Catalog\Product\VariantInput;
use App\Application\Catalog\Station\CreateStation;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Attendance\LocationId;
use App\Domain\Attendance\LocationType;
use App\Domain\Catalog\CategoryId;
use App\Domain\Catalog\ModifierGroupId;
use App\Domain\Catalog\ModifierId;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductVariantId;
use App\Domain\Catalog\StationId;
use App\Domain\Identity\CompanyNotFound;
use App\Domain\Identity\CompanyRepository;
use App\Domain\Identity\Email;
use App\Domain\Identity\Slug;
use App\Domain\Identity\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * bin/console app:seed-demo-catalog [--extra=1000] — cardápio e mesas da empresa demo.
 *
 * Um cardápio de verdade (Chopp, X-Burger, Caipirinha...) + `--extra` produtos
 * sintéticos para medir a busca (aceite M2.4: `q=cho` em < 50 ms com 1k).
 * Tudo pelos casos de uso, com o dono da demo como ator: passa pelas mesmas
 * regras e permissões da API. Idempotente: filial que já tem categoria não
 * ganha cardápio de novo; filial que já tem local não ganha mesas de novo.
 */
#[AsCommand(name: 'app:seed-demo-catalog', description: 'Cria o cardápio e as mesas da empresa demo (rode app:seed-demo antes)')]
final readonly class SeedDemoCatalogCommand
{
    private const ACTOR_EMAIL = 'dono@demo.com';
    private const SYNTHETIC_BASES = ['Pizza', 'Suco', 'Porção', 'Espetinho', 'Salada', 'Torta', 'Drink', 'Pastel', 'Wrap', 'Sanduíche'];
    // sem sabores começando com "cho": a busca de aceite (q=cho) mira o Chopp
    private const SYNTHETIC_FLAVORS = ['Calabresa', 'Frango', 'Queijo', 'Laranja', 'Morango', 'Carne', 'Palmito', 'Brigadeiro', 'Limão', 'Bacon', 'Atum', 'Milho'];

    public function __construct(
        private CommandBus $bus,
        private CompanyRepository $companies,
        private UserRepository $users,
        private CatalogQuery $catalog,
        private AttendanceQuery $attendance,
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.environment%')] private string $environment,
    ) {
    }

    public function __invoke(
        SymfonyStyle $console,
        #[Option('Produtos sintéticos a mais (teste de busca)')] int $extra = 0,
    ): int {
        if ('prod' === $this->environment) {
            $console->error('Seed de demonstração não roda em produção.');

            return Command::FAILURE;
        }

        try {
            $company = $this->companies->getBySlug(Slug::fromString(SeedDemoCommand::SLUG));
        } catch (CompanyNotFound) {
            $console->error('Empresa demo não existe: rode `bin/console app:seed-demo` antes.');

            return Command::FAILURE;
        }

        $owner = $this->users->findByEmail($company->id(), Email::fromString(self::ACTOR_EMAIL)) ?? throw new \LogicException('Dono da demo não encontrado.');
        $actorId = $owner->id()->toString();
        $branchId = $company->branches()[0]->id();

        $branch = $branchId->toString();

        if ([] === $this->attendance->locations($company->id(), $branchId)) {
            $this->bus->dispatch(new CreateLocation(LocationId::generate()->toString(), $actorId, $branch, LocationType::Counter->value, 'Balcão'));
            for ($table = 1; $table <= 10; ++$table) {
                $this->bus->dispatch(new CreateLocation(LocationId::generate()->toString(), $actorId, $branch, LocationType::Table->value, "Mesa {$table}"));
            }
            $console->success('Locais da demo: Balcão e Mesa 1 a 10.');
        }

        if ([] !== $this->catalog->categories($company->id(), $branchId)) {
            $console->note('A filial da demo já tem cardápio — nada a fazer.');

            return Command::SUCCESS;
        }

        $categories = [];
        foreach (['Bebidas', 'Lanches', 'Porções', 'Sobremesas'] as $position => $name) {
            $categories[$name] = CategoryId::generate()->toString();
            $this->bus->dispatch(new CreateCategory($categories[$name], $actorId, $branch, $name, $position));
        }
        $stations = [];
        foreach (['Cozinha', 'Bar'] as $name) {
            $stations[$name] = StationId::generate()->toString();
            $this->bus->dispatch(new CreateStation($stations[$name], $actorId, $branch, $name));
        }

        $menu = $this->menu($categories, $stations);
        foreach ($menu as [$name, $category, $station, $variants, $groups]) {
            $this->bus->dispatch(new CreateProduct(ProductId::generate()->toString(), $actorId, $branch, $name, $variants, $groups, $category, $station));
        }

        $console->progressStart($extra);
        for ($index = 1; $index <= $extra; ++$index) {
            $name = \sprintf('%s %s %d', self::SYNTHETIC_BASES[$index % \count(self::SYNTHETIC_BASES)], self::SYNTHETIC_FLAVORS[$index % \count(self::SYNTHETIC_FLAVORS)], $index);
            $this->bus->dispatch(new CreateProduct(ProductId::generate()->toString(), $actorId, $branch, $name, [self::variant($name, 1000 + $index % 50 * 100)]));

            // a Unit of Work guarda tudo que já gravou: esvaziar de tempos em tempos mantém o seed rápido
            if (0 === $index % 100) {
                $this->entityManager->clear();
            }
            $console->progressAdvance();
        }
        $console->progressFinish();

        $console->success(\sprintf('Cardápio da demo: %d categorias, %d estações, %d produtos.', \count($categories), \count($stations), \count($menu) + $extra));

        return Command::SUCCESS;
    }

    /**
     * @param array<string, string> $categories
     * @param array<string, string> $stations
     *
     * @return list<array{string, string, ?string, list<VariantInput>, list<ModifierGroupInput>}>
     */
    private function menu(array $categories, array $stations): array
    {
        return [
            ['Chopp', $categories['Bebidas'], $stations['Bar'], [self::variant('300ml', 1200), self::variant('500ml', 1800)], []],
            ['Refrigerante lata', $categories['Bebidas'], null, [self::variant('Lata 350ml', 700)], []],
            ['Caipirinha', $categories['Bebidas'], $stations['Bar'], [self::variant('Caipirinha', 2200)], [
                self::group('Fruta', 1, 1, ['Limão' => 0, 'Morango' => 300, 'Maracujá' => 300]),
            ]],
            ['X-Burger', $categories['Lanches'], $stations['Cozinha'], [self::variant('X-Burger', 3200)], [
                self::group('Ponto', 1, 1, ['Mal passado' => 0, 'Ao ponto' => 0, 'Bem passado' => 0]),
                self::group('Adicionais', 0, 5, ['Bacon' => 400, 'Ovo' => 300, 'Cheddar' => 350]),
            ]],
            ['X-Salada', $categories['Lanches'], $stations['Cozinha'], [self::variant('X-Salada', 2900)], [
                self::group('Ponto', 1, 1, ['Ao ponto' => 0, 'Bem passado' => 0]),
            ]],
            ['Batata frita', $categories['Porções'], $stations['Cozinha'], [self::variant('Pequena', 1900), self::variant('Grande', 2900)], []],
            ['Pudim', $categories['Sobremesas'], null, [self::variant('Fatia', 1200)], []],
        ];
    }

    private static function variant(string $name, int $priceCents): VariantInput
    {
        return new VariantInput(ProductVariantId::generate()->toString(), $name, $priceCents);
    }

    /** @param array<string, int> $modifiers nome => delta em centavos */
    private static function group(string $name, int $minSelect, int $maxSelect, array $modifiers): ModifierGroupInput
    {
        $inputs = [];
        foreach ($modifiers as $modifierName => $delta) {
            $inputs[] = new ModifierInput(ModifierId::generate()->toString(), $modifierName, $delta);
        }

        return new ModifierGroupInput(ModifierGroupId::generate()->toString(), $name, $minSelect, $maxSelect, $inputs);
    }
}
