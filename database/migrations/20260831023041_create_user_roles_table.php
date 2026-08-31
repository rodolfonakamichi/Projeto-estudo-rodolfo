<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Vínculo usuário <-> papel, opcionalmente restrito a uma filial.
 * PK composta (user_id, role_id, branch_id).
 *
 * branch_id = '' (default) => papel vale na empresa toda.
 * branch_id = <ULID>        => papel só naquela filial.
 * Sentinela em vez de NULL porque parte de PK não pode ser nula (MySQL 1171).
 * Sem FK em branch_id: o '' não é filial real — a aplicação valida.
 */
final class CreateUserRolesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('user_roles', ['id' => false, 'primary_key' => ['user_id', 'role_id', 'branch_id']])
            ->addColumn('user_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('role_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('branch_id', 'char', ['limit' => 26, 'null' => false, 'default' => ''])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
