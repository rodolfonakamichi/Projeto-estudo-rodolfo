<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Identity\CompanyContextHolder;

/**
 * Base dos repositórios PDO. Toda leitura/escrita de tabela de negócio passa
 * pelos helpers daqui, que injetam `company_id = :__cid` automaticamente
 * (ADR-004). Nenhum repositório deve montar SQL de negócio sem esse filtro.
 */
abstract class PdoRepository
{
    public function __construct(
        protected readonly \PDO $pdo,
        private readonly CompanyContextHolder $contextHolder,
    ) {
    }

    protected function companyId(): string
    {
        return $this->contextHolder->get()->companyId->toString();
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @return array<string, mixed>|null
     */
    protected function fetchOneScoped(string $table, string $whereSql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$table} WHERE company_id = :__cid AND ({$whereSql}) LIMIT 1",
        );
        $stmt->execute([...$params, '__cid' => $this->companyId()]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @return list<array<string, mixed>>
     */
    protected function fetchAllScoped(string $table, string $whereSql = '1=1', array $params = []): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$table} WHERE company_id = :__cid AND ({$whereSql})",
        );
        $stmt->execute([...$params, '__cid' => $this->companyId()]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }
}
