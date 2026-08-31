<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCountersTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $this->table('counters', ['id' => false, 'primary_key' => ['company_id', 'branch_id', 'scope']])
            ->addColumn('company_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('branch_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('scope', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('value', 'integer', ['null' => false, 'default' => 0])
            ->create();
    }
}
