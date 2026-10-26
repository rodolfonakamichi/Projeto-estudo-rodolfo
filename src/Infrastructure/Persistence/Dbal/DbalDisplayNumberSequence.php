<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Attendance\Command\DisplayNumberSequence;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use Doctrine\DBAL\Connection;

/**
 * Número da comanda por filial e data operacional (doc 03 §8), seguro contra
 * corrida: um único INSERT ... ON DUPLICATE KEY UPDATE trava a linha do
 * contador até o COMMIT da transação do caso de uso, e o LAST_INSERT_ID(expr)
 * devolve o valor desta conexão — dois garçons nunca recebem o mesmo número.
 *
 * A tabela `counters` não é entidade (fica fora do ORM via `schema_filter`).
 */
final readonly class DbalDisplayNumberSequence implements DisplayNumberSequence
{
    public function __construct(private Connection $db)
    {
    }

    public function next(CompanyId $companyId, BranchId $branchId, \DateTimeImmutable $businessDate): int
    {
        $this->db->executeStatement(
            'INSERT INTO counters (company_id, branch_id, scope, value) VALUES (:company, :branch, :scope, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE value = LAST_INSERT_ID(value + 1)',
            ['company' => $companyId->toString(), 'branch' => $branchId->toString(), 'scope' => 'command:'.$businessDate->format('Y-m-d')],
        );

        return (int) $this->db->lastInsertId();
    }
}
