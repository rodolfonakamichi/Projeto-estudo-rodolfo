<?php

declare(strict_types=1);

namespace App\Application\Identity\RegisterCompany;

use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Company;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\CompanyRepository;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\RoleRepository;
use App\Domain\Identity\Slug;
use App\Domain\Shared\DomainException;

/**
 * Caso de uso: orquestra (valida unicidade, chama a factory, salva, cria os
 * papéis padrão). A regra
 * da empresa em si mora em Company. O COMMIT é do middleware do command.bus.
 */
final readonly class RegisterCompanyHandler implements CommandHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private RoleRepository $roles,
    ) {
    }

    public function __invoke(RegisterCompany $command): void
    {
        $slug = Slug::fromString($command->slug);

        if ($this->companies->slugExists($slug)) {
            throw new DomainException("O slug \"{$command->slug}\" já está em uso.");
        }

        $company = Company::register(
            CompanyId::fromString($command->companyId),
            $command->name,
            $slug,
            BranchId::fromString($command->branchId),
            $command->branchName,
            $command->timezone,
        );

        foreach ($command->capabilities as $capability) {
            $company->enableCapability($capability);
        }

        $this->companies->save($company);

        // toda empresa nasce com os papéis padrão do doc 06 (template de negócio)
        foreach (DefaultRoles::for($company) as $role) {
            $this->roles->save($role);
        }
    }
}
