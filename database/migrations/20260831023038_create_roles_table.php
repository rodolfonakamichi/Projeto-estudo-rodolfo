<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Papéis por empresa (OWNER, ADMIN, MANAGER, CASHIER, WAITER, KITCHEN, BAR,
 * STOCK, AUDITOR). Cada papel agrupa permissões em `role_permissions`.
 * Contrato: docs/dominio/03 seção 1 + docs/dominio/06 (matriz).
 */
final class CreateRolesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('roles', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('code', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 80, 'null' => false])
            ->addTimestamps()
            ->addIndex(['company_id', 'code'], ['unique' => true])
            ->addForeignKey('company_id', 'companies', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
