<?php

declare(strict_types=1);

namespace App\Application\Identity\RegisterUser;

/** Papel a atribuir: código + filial (`null` = empresa toda). */
final readonly class RoleAssignment
{
    public function __construct(
        public string $roleCode,
        public ?string $branchId = null,
    ) {
    }
}
