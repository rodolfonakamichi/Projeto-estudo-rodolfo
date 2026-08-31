<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Permissões concedidas a cada papel. Uma linha = uma permissão de um papel.
 * PK composta (role_id, permission) — sem coluna `id` própria.
 * Lista de permissões: docs/dominio/06-matriz-de-permissoes.md, seção 2.
 */
final class CreateRolePermissionsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('role_permissions', ['id' => false, 'primary_key' => ['role_id', 'permission']])
            ->addColumn('role_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('permission', 'string', ['limit' => 60, 'null' => false])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
