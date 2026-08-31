<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Usuários que operam o sistema (dono, garçom, caixa, cozinha).
 * Contrato: docs/dominio/03-modelo-de-dados.md, seção 1.
 */
final class CreateUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 150, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 190, 'null' => false])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => false])
            // login rápido por PIN no PWA — opcional
            ->addColumn('pin_hash', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'ACTIVE'])
            ->addTimestamps()
            ->addIndex(['company_id', 'email'], ['unique' => true])
            ->addForeignKey('company_id', 'companies', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
