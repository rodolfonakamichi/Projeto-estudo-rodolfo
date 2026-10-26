<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Http\Catalog;

use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Identity\RegisterUser\RegisterUser;
use App\Application\Identity\RegisterUser\RoleAssignment;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Capability;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\CompanyRepository;
use App\Domain\Identity\Permission;
use App\Domain\Identity\Role;
use App\Domain\Identity\RoleCode;
use App\Domain\Identity\RoleId;
use App\Domain\Identity\RoleRepository;
use App\Domain\Identity\UserId;
use App\Tests\Presentation\Http\JsonApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * M2.3: produto pela API, com os aceites do doc 07 (Chopp, X-Burger).
 * "CARDAPISTA" é um papel próprio só com `catalog.product.manage` — prova que
 * mexer em preço pede também `catalog.price.manage`.
 */
final class ProductApiTest extends WebTestCase
{
    use JsonApiClient;

    private string $branch;
    private string $drinks;
    private string $bar;
    private string $manager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::getContainer()->get('cache.rate_limiter')->clear();

        $companyId = CompanyId::generate();
        $this->branch = BranchId::generate()->toString();
        $bus = self::getContainer()->get(CommandBus::class);
        $bus->dispatch(new RegisterCompany($companyId->toString(), 'Bar do Zé', 'bar-do-ze', $this->branch, 'Matriz', capabilities: [Capability::Kitchen]));

        $roles = self::getContainer()->get(RoleRepository::class);
        $company = self::getContainer()->get(CompanyRepository::class)->get($companyId);
        $roles->save(Role::create(RoleId::generate(), $company, RoleCode::fromString('CARDAPISTA'), 'Cardapista', [Permission::CatalogProductManage]));
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        foreach (['gil@bar.com' => 'MANAGER', 'ana@bar.com' => 'WAITER', 'cris@bar.com' => 'CARDAPISTA'] as $email => $role) {
            $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $companyId->toString(), 'Pessoa', $email, 'segredo123', [new RoleAssignment($role, $this->branch)]));
        }

        // empresa B cadastrada ANTES da 1ª requisição: depois dela o container do
        // teste fica com o contexto (e o filtro) da empresa A até o próximo request
        $outsiderCompany = CompanyId::generate()->toString();
        $bus->dispatch(new RegisterCompany($outsiderCompany, 'Salão da Lu', 'salao-da-lu', BranchId::generate()->toString(), 'Centro'));
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $outsiderCompany, 'Lu', 'lu@salao.com', 'segredo123', [new RoleAssignment('OWNER')]));

        $this->manager = $this->login('bar-do-ze', 'gil@bar.com');
        $this->drinks = $this->json('POST', "/api/branches/{$this->branch}/categories", ['name' => 'Bebidas'], $this->manager)['id'];
        $this->bar = $this->json('POST', "/api/branches/{$this->branch}/stations", ['name' => 'Bar'], $this->manager)['id'];
    }

    /** Aceite M2: Chopp com variações 300ml/500ml (o "Tamanho" é a escolha da variação). */
    public function test_aceite_chopp_300_e_500ml(): void
    {
        $chopp = $this->json('POST', "/api/branches/{$this->branch}/products", [
            'name' => 'Chopp',
            'categoryId' => $this->drinks,
            'stationId' => $this->bar,
            'variants' => [['name' => '300ml', 'priceCents' => 1200], ['name' => '500ml', 'priceCents' => 1800]],
        ], $this->manager);

        self::assertResponseStatusCodeSame(201);
        self::assertSame([['300ml', 1200], ['500ml', 1800]], array_map(static fn (array $variant): array => [$variant['name'], $variant['priceCents']], $chopp['variants']));
        self::assertSame($this->bar, $chopp['stationId']);

        $byCategory = $this->json('GET', "/api/branches/{$this->branch}/products?categoryId={$this->drinks}", token: $this->manager);
        self::assertSame(['Chopp'], array_column($byCategory['products'], 'name'));
    }

    /** Aceite M2: X-Burger com "Ponto" (1 obrigatório) e "Adicionais" (0–5). */
    public function test_aceite_x_burger_com_ponto_e_adicionais(): void
    {
        $burger = $this->createBurger();

        self::assertResponseStatusCodeSame(201);
        [$doneness, $extras] = $burger['modifierGroups'];
        self::assertSame(['Ponto', 1, 1, true], [$doneness['name'], $doneness['minSelect'], $doneness['maxSelect'], $doneness['required']]);
        self::assertSame(['Adicionais', 0, 5, false], [$extras['name'], $extras['minSelect'], $extras['maxSelect'], $extras['required']]);
        self::assertSame([400, 300], array_column($extras['modifiers'], 'priceDeltaCents'));
    }

    public function test_regras_do_dominio_viram_422_com_a_mensagem(): void
    {
        $impossible = $this->json('POST', "/api/branches/{$this->branch}/products", [
            'name' => 'X-Salada',
            'variants' => [['name' => 'X-Salada', 'priceCents' => 2800]],
            'modifierGroups' => [['name' => 'Molhos', 'minSelect' => 2, 'maxSelect' => 2, 'modifiers' => [['name' => 'Maionese']]]],
        ], $this->manager);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('só tem 1 opção', $impossible['error']);

        $withoutVariant = $this->json('POST', "/api/branches/{$this->branch}/products", ['name' => 'Água', 'variants' => []], $this->manager);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('variants', $withoutVariant['fields']);

        $negative = $this->json('POST', "/api/branches/{$this->branch}/products", ['name' => 'Água', 'variants' => [['name' => 'Água', 'priceCents' => -1]]], $this->manager);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('variants[0].priceCents', $negative['fields']);
    }

    public function test_mexer_em_preco_exige_catalog_price_manage(): void
    {
        $menuEditor = $this->login('bar-do-ze', 'cris@bar.com');

        $this->json('POST', "/api/branches/{$this->branch}/products", ['name' => 'Água', 'variants' => [['name' => 'Água', 'priceCents' => 500]]], $menuEditor);
        self::assertResponseStatusCodeSame(403, 'criar produto define preço');

        $burger = $this->createBurger();
        $variantId = $burger['variants'][0]['id'];

        $renamed = $this->json('PATCH', "/api/products/{$burger['id']}", ['name' => 'X-Burger Artesanal'], $menuEditor);
        self::assertResponseIsSuccessful();
        self::assertSame('X-Burger Artesanal', $renamed['name']);

        $this->json('PATCH', "/api/products/{$burger['id']}/variants/{$variantId}", ['priceCents' => 100], $menuEditor);
        self::assertResponseStatusCodeSame(403);

        $repriced = $this->json('PATCH', "/api/products/{$burger['id']}/variants/{$variantId}", ['priceCents' => 3500], $this->manager);
        self::assertSame(3500, $repriced['variants'][0]['priceCents']);
    }

    public function test_patch_distingue_campo_ausente_de_null(): void
    {
        $chopp = $this->json('POST', "/api/branches/{$this->branch}/products", [
            'name' => 'Chopp',
            'categoryId' => $this->drinks,
            'stationId' => $this->bar,
            'variants' => [['name' => '300ml', 'priceCents' => 1200]],
        ], $this->manager);

        $updated = $this->json('PATCH', "/api/products/{$chopp['id']}", ['categoryId' => null], $this->manager);

        self::assertNull($updated['categoryId'], '"categoryId": null limpa');
        self::assertSame($this->bar, $updated['stationId'], 'stationId ausente não mexe');
    }

    public function test_variacoes_e_modificadores(): void
    {
        $burger = $this->createBurger();
        $onlyVariant = $burger['variants'][0]['id'];

        $this->json('PATCH', "/api/products/{$burger['id']}/variants/{$onlyVariant}", ['active' => false], $this->manager);
        self::assertResponseStatusCodeSame(422, 'única variação ativa');

        $withDouble = $this->json('POST', "/api/products/{$burger['id']}/variants", ['name' => 'Duplo', 'priceCents' => 4200], $this->manager);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['X-Burger', 'Duplo'], array_column($withDouble['variants'], 'name'));

        [$doneness, $extras] = $burger['modifierGroups'];
        $bacon = $extras['modifiers'][0]['id'];
        $afterBacon = $this->json('PATCH', "/api/products/{$burger['id']}/modifier-groups/{$extras['id']}/modifiers/{$bacon}", ['active' => false], $this->manager);
        self::assertFalse($afterBacon['modifierGroups'][1]['modifiers'][0]['active']);

        $this->json('PATCH', "/api/products/{$burger['id']}/modifier-groups/{$doneness['id']}", ['minSelect' => 4, 'maxSelect' => 4], $this->manager);
        self::assertResponseStatusCodeSame(422, 'Ponto só tem 3 opções');

        $withSauce = $this->json('POST', "/api/products/{$burger['id']}/modifier-groups", ['name' => 'Molho', 'minSelect' => 0, 'maxSelect' => 1, 'modifiers' => [['name' => 'Barbecue', 'priceDeltaCents' => 200]]], $this->manager);
        self::assertSame(['Ponto', 'Adicionais', 'Molho'], array_column($withSauce['modifierGroups'], 'name'));
    }

    public function test_categoria_arquivada_e_garcom_so_le(): void
    {
        $this->json('DELETE', "/api/categories/{$this->drinks}", token: $this->manager);
        $this->json('POST', "/api/branches/{$this->branch}/products", ['name' => 'Chopp', 'categoryId' => $this->drinks, 'variants' => [['name' => '300ml', 'priceCents' => 1200]]], $this->manager);
        self::assertResponseStatusCodeSame(422);

        $burger = $this->createBurger();
        $waiter = $this->login('bar-do-ze', 'ana@bar.com');

        $this->json('GET', "/api/products/{$burger['id']}", token: $waiter);
        self::assertResponseIsSuccessful();

        $this->json('PATCH', "/api/products/{$burger['id']}", ['name' => 'X-Garçom'], $waiter);
        self::assertResponseStatusCodeSame(403);
    }

    public function test_outra_empresa_nao_ve_o_produto(): void
    {
        $burger = $this->createBurger();
        $outsider = $this->login('salao-da-lu', 'lu@salao.com');

        $this->json('GET', "/api/products/{$burger['id']}", token: $outsider);
        self::assertResponseStatusCodeSame(404);

        $this->json('PATCH', "/api/products/{$burger['id']}", ['name' => 'Invadido'], $outsider);
        self::assertResponseStatusCodeSame(404);
    }

    /** @return array<string, mixed> */
    private function createBurger(): array
    {
        return $this->json('POST', "/api/branches/{$this->branch}/products", [
            'name' => 'X-Burger',
            'variants' => [['name' => 'X-Burger', 'priceCents' => 3200]],
            'modifierGroups' => [
                ['name' => 'Ponto', 'minSelect' => 1, 'maxSelect' => 1, 'modifiers' => [['name' => 'Mal passado'], ['name' => 'Ao ponto'], ['name' => 'Bem passado']]],
                ['name' => 'Adicionais', 'minSelect' => 0, 'maxSelect' => 5, 'modifiers' => [['name' => 'Bacon', 'priceDeltaCents' => 400], ['name' => 'Ovo', 'priceDeltaCents' => 300]]],
            ],
        ], $this->manager);
    }
}
