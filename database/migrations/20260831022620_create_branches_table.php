<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateBranchesTable extends AbstractMigration
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
        $this->table('branches', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 150, 'null' => false])
            ->addColumn('timezone', 'string', ['limit' => 40, 'null' => false, 'default' =>
                'America/Sao_Paulo'])
            ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'ACTIVE'])
            ->addTimestamps()
            ->addIndex(['company_id', 'name'], ['unique' => true])
            ->addForeignKey('company_id', 'companies', 'id', ['delete' => 'CASCADE', 'update' =>
                'NO_ACTION'])
            ->create();
    }
}
