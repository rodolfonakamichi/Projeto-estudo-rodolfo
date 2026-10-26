<?php

declare(strict_types=1);

namespace App\Presentation\Console\Identity;

use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Capability;
use App\Domain\Identity\CompanyId;
use App\Domain\Shared\DomainException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Adaptador de entrada (CLI) do caso de uso RegisterCompany. Não tem regra:
 * traduz argumentos em Command e despacha.
 *
 *   bin/console app:company:register "Bar do Zé" bar-do-ze --capability=tables --capability=kitchen
 */
#[AsCommand(name: 'app:company:register', description: 'Cadastra uma empresa com a filial inicial')]
final readonly class RegisterCompanyCommand
{
    public function __construct(private CommandBus $bus)
    {
    }

    /**
     * @param list<string> $capability
     */
    public function __invoke(
        SymfonyStyle $console,
        #[Argument('Nome da empresa')] string $name,
        #[Argument('Slug único (minúsculas, dígitos, hífen)')] string $slug,
        #[Option('Nome da primeira filial')] string $branch = 'Matriz',
        #[Option('Fuso horário da filial')] string $timezone = 'America/Sao_Paulo',
        #[Option('Capability a ligar (repetível): tables, kitchen, inventory...')] array $capability = [],
    ): int {
        $companyId = CompanyId::generate();
        $branchId = BranchId::generate();

        try {
            $this->bus->dispatch(new RegisterCompany(
                companyId: $companyId->toString(),
                name: $name,
                slug: $slug,
                branchId: $branchId->toString(),
                branchName: $branch,
                timezone: $timezone,
                capabilities: array_map(self::capability(...), $capability),
            ));
        } catch (DomainException|\InvalidArgumentException $exception) {
            $console->error($exception->getMessage());

            return Command::FAILURE;
        }

        $console->success("Empresa {$companyId} cadastrada (filial {$branchId}).");

        return Command::SUCCESS;
    }

    private static function capability(string $value): Capability
    {
        return Capability::tryFrom($value) ?? throw new \InvalidArgumentException(\sprintf('Capability "%s" não existe. Válidas: %s.', $value, implode(', ', array_map(static fn (Capability $capability): string => $capability->value, Capability::cases()))));
    }
}
