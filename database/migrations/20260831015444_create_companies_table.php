<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Tabela raiz do multiempresa: cada linha é uma empresa cliente da plataforma.
 * Tudo que vier depois (units, users, products, commands...) referencia
 * `companies.id` via `company_id` (ADR-004).
 *
 * Contrato: docs/dominio/03-modelo-de-dados.md, seção 1.
 */
final class CreateCompaniesTable extends AbstractMigration
{
    public function change(): void
    {
        // ['id' => false, 'primary_key' => ['id']]:
        //   'id' => false           desliga o id auto-increment padrão do Phinx
        //   'primary_key' => ['id'] usa nossa coluna 'id' (o ULID) como PK
        $this->table('companies', ['id' => false, 'primary_key' => ['id']])
            // ULID = CHAR(26), gerado pela aplicação (ADR-005).
            // 'null' => false SEMPRE — o Phinx 0.16 deixa nullable por padrão,
            // e coluna de PK não pode ser nullable (erro MySQL 1171).
            ->addColumn('id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 150, 'null' => false])
            ->addColumn('slug', 'string', ['limit' => 80, 'null' => false])
            // status como VARCHAR (ADR-007: nada de ENUM no banco)
            ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'ACTIVE'])
            // cria created_at (default CURRENT_TIMESTAMP) e updated_at
            ->addTimestamps()
            // slug é único em toda a plataforma (não há empresa acima dele)
            ->addIndex(['slug'], ['unique' => true])
            ->create();
        // change() => o Phinx deduz o rollback sozinho (DROP TABLE companies)
    }
}
