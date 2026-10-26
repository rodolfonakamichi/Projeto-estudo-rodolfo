<?php

declare(strict_types=1);

namespace App\Domain\Identity;

interface RoleRepository
{
    public function save(Role $role): void;

    public function getByCode(CompanyId $companyId, RoleCode $code): Role;
}
