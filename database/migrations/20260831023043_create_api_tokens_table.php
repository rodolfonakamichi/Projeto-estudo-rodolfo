<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Tokens de acesso (bearer). Guardamos só o SHA-256 do token; o valor em
 * claro aparece uma única vez, na criação.
 *
 * Autenticação (M1.f): Authorization: Bearer <token> -> hash -> busca aqui
 * -> resolve user_id + company_id -> monta o CompanyContext.
 * Contrato: docs/dominio/03-modelo-de-dados.md, seção 1.
 */
final class CreateApiTokensTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('api_tokens', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('user_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('token_hash', 'char', ['limit' => 64, 'null' => false])
            ->addColumn('last_used_at', 'datetime', ['null' => true])
            ->addColumn('expires_at', 'datetime', ['null' => true])
            ->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['token_hash'], ['unique' => true])
            ->addIndex(['user_id'])
            ->addForeignKey('company_id', 'companies', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
