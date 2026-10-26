<?php

declare(strict_types=1);

namespace App\Presentation\Console\Demo;

use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Identity\RegisterUser\RegisterUser;
use App\Application\Identity\RegisterUser\RoleAssignment;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Capability;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\CompanyRepository;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Slug;
use App\Domain\Identity\UserId;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * bin/console app:seed-demo — empresa de demonstração para testar a API à mão.
 *
 * Não grava no banco por SQL: despacha os MESMOS casos de uso da API/CLI
 * (RegisterCompany, RegisterUser), então o seed passa pelas regras do Domain.
 * Tudo numa transação (os casos de uso viram savepoints): ou sai inteiro ou nada.
 *
 * Idempotente: se a empresa `demo` já existe, não faz nada.
 */
#[AsCommand(name: 'app:seed-demo', description: 'Cria a empresa demo (1 filial, 1 usuário por papel)')]
final readonly class SeedDemoCommand
{
    public const SLUG = 'demo';
    public const PASSWORD = 'demo1234';
    public const PIN = '1234';

    /**
     * Quem administra vale na empresa toda; quem opera fica na filial e loga
     * por PIN no PWA.
     *
     * @var array<string, array{email: string, companyWide: bool, pin: ?string}>
     */
    private const USERS = [
        'OWNER' => ['email' => 'dono@demo.com', 'companyWide' => true, 'pin' => null],
        'ADMIN' => ['email' => 'admin@demo.com', 'companyWide' => true, 'pin' => null],
        'AUDITOR' => ['email' => 'auditor@demo.com', 'companyWide' => true, 'pin' => null],
        'MANAGER' => ['email' => 'gerente@demo.com', 'companyWide' => false, 'pin' => null],
        'CASHIER' => ['email' => 'caixa@demo.com', 'companyWide' => false, 'pin' => self::PIN],
        'WAITER' => ['email' => 'garcom@demo.com', 'companyWide' => false, 'pin' => self::PIN],
        'KITCHEN' => ['email' => 'cozinha@demo.com', 'companyWide' => false, 'pin' => self::PIN],
        'BAR' => ['email' => 'bar@demo.com', 'companyWide' => false, 'pin' => self::PIN],
        'STOCK' => ['email' => 'estoque@demo.com', 'companyWide' => false, 'pin' => null],
    ];

    public function __construct(
        private CommandBus $bus,
        private CompanyRepository $companies,
        private Connection $db,
        #[Autowire('%kernel.environment%')] private string $environment,
    ) {
    }

    public function __invoke(SymfonyStyle $console): int
    {
        if ('prod' === $this->environment) {
            $console->error('Seed de demonstração não roda em produção (senhas conhecidas).');

            return Command::FAILURE;
        }

        if ($this->companies->slugExists(Slug::fromString(self::SLUG))) {
            $console->note('A empresa "demo" já existe — nada a fazer.');

            return Command::SUCCESS;
        }

        $companyId = CompanyId::generate();
        $branchId = BranchId::generate();
        $roleNames = array_map(static fn (array $definition): string => $definition[0], DefaultRoles::matrix());

        $this->db->transactional(function () use ($companyId, $branchId, $roleNames): void {
            $this->bus->dispatch(new RegisterCompany(
                companyId: $companyId->toString(),
                name: 'Bar Demo',
                slug: self::SLUG,
                branchId: $branchId->toString(),
                branchName: 'Matriz',
                capabilities: [Capability::Tables, Capability::Kitchen],
            ));

            foreach (self::USERS as $roleCode => $user) {
                $this->bus->dispatch(new RegisterUser(
                    userId: UserId::generate()->toString(),
                    companyId: $companyId->toString(),
                    name: $roleNames[$roleCode],
                    email: $user['email'],
                    password: self::PASSWORD,
                    roles: [new RoleAssignment($roleCode, $user['companyWide'] ? null : $branchId->toString())],
                    pin: $user['pin'],
                ));
            }
        });

        $rows = [];
        foreach (self::USERS as $roleCode => $user) {
            $rows[] = [$roleCode, $user['email'], $user['companyWide'] ? 'empresa toda' : 'Matriz', $user['pin'] ?? '—'];
        }

        $console->success(\sprintf('Empresa "%s" criada — filial Matriz %s.', self::SLUG, $branchId));
        $console->table(['Papel', 'E-mail', 'Escopo', 'PIN'], $rows);
        $console->text(\sprintf('Senha de todos: %s. Login: POST /api/auth/login {"company":"%s", ...}', self::PASSWORD, self::SLUG));

        return Command::SUCCESS;
    }
}
