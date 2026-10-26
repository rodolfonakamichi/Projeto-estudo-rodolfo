<?php

declare(strict_types=1);

namespace App\Presentation\Console\Identity;

use App\Application\Identity\RegisterUser\RegisterUser;
use App\Application\Identity\RegisterUser\RoleAssignment;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\CompanyRepository;
use App\Domain\Identity\Slug;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * bin/console app:user:register bar-do-ze "Ana" ana@bar.com --role=CASHIER --role=WAITER@<branchId> --pin=1234.
 *
 * `--role=CODIGO` vale na empresa toda; `--role=CODIGO@<branchId>` só naquela filial.
 * Sem `--password`, a senha é pedida sem eco no terminal.
 */
#[AsCommand(name: 'app:user:register', description: 'Cadastra um usuário numa empresa')]
final readonly class RegisterUserCommand
{
    public function __construct(
        private CommandBus $bus,
        private CompanyRepository $companies,
    ) {
    }

    /**
     * @param list<string> $role
     */
    public function __invoke(
        SymfonyStyle $console,
        #[Argument('Slug da empresa')] string $company,
        #[Argument('Nome')] string $name,
        #[Argument('E-mail')] string $email,
        #[Option('Senha (se omitida, é perguntada)')] ?string $password = null,
        #[Option('Papel, repetível: CODIGO ou CODIGO@<branchId>')] array $role = [],
        #[Option('PIN de 4 a 6 dígitos para o PWA')] ?string $pin = null,
    ): int {
        $userId = UserId::generate();

        try {
            $companyId = $this->companies->getBySlug(Slug::fromString($company))->id();
            $password ??= (string) $console->askHidden('Senha');

            $this->bus->dispatch(new RegisterUser(
                userId: $userId->toString(),
                companyId: $companyId->toString(),
                name: $name,
                email: $email,
                password: $password,
                roles: array_map(self::assignment(...), $role),
                pin: $pin,
            ));
        } catch (DomainException|\InvalidArgumentException $exception) {
            $console->error($exception->getMessage());

            return Command::FAILURE;
        }

        $console->success("Usuário {$userId} cadastrado.");

        return Command::SUCCESS;
    }

    private static function assignment(string $value): RoleAssignment
    {
        $parts = explode('@', $value, 2);

        return new RoleAssignment($parts[0], $parts[1] ?? null);
    }
}
