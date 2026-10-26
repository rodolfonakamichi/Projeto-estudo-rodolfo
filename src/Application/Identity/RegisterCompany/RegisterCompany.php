<?php

declare(strict_types=1);

namespace App\Application\Identity\RegisterCompany;

use App\Application\Shared\Bus\Command;
use App\Domain\Identity\Capability;

/**
 * Cadastra uma empresa cliente com a primeira filial. O chamador gera os IDs
 * (ULID no cliente): assim ele sabe o ID sem precisar de retorno do bus.
 */
final readonly class RegisterCompany implements Command
{
    /**
     * @param list<Capability> $capabilities
     */
    public function __construct(
        public string $companyId,
        public string $name,
        public string $slug,
        public string $branchId,
        public string $branchName,
        public string $timezone = 'America/Sao_Paulo',
        public array $capabilities = [],
    ) {
    }
}
