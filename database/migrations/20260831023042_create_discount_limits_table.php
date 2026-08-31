<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Faixa máxima de desconto que cada papel pode aplicar sem autorização
 * (docs/dominio/06, seção 4). max_percent NULL = sem limite.
 * Uma linha por (empresa, papel).
 */
final class CreateDiscountLimitsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('discount_limits', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('role_code', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('max_percent', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true])
            ->addTimestamps()
            ->addIndex(['company_id', 'role_code'], ['unique' => true])
            ->addForeignKey('company_id', 'companies', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
