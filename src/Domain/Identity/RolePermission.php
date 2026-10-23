<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Linha de `role_permissions` (PK composta role_id + permission). Parte do
 * agregado Role; de fora só se usa `Role::grants()`.
 *
 * A permissão fica como string porque o Doctrine não aceita enum em campo de
 * chave primária; a conversão para o enum acontece aqui dentro.
 *
 * @internal
 */
class RolePermission
{
    private string $permission; // parte da PK: sem readonly (proxy do Doctrine)

    public function __construct(
        private Role $role, // parte da PK: sem readonly (proxy do Doctrine)
        Permission $permission,
    ) {
        $this->permission = $permission->value;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function permission(): Permission
    {
        return Permission::from($this->permission);
    }
}
