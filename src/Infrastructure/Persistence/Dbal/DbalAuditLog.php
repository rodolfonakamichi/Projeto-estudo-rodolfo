<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Audit\AuditEntry;
use App\Application\Audit\AuditLog;
use Doctrine\DBAL\Connection;

/** `audit_logs` só por SQL (fora do ORM, `schema_filter`): é escrita uma vez e nunca alterada. */
final readonly class DbalAuditLog implements AuditLog
{
    public function __construct(private Connection $db)
    {
    }

    public function record(AuditEntry $entry): void
    {
        // INSERT IGNORE: o mesmo evento reprocessado (id igual) não duplica a linha
        $this->db->executeStatement(<<<'SQL'
            INSERT IGNORE INTO audit_logs
                (id, company_id, branch_id, actor_id, actor_name, action, target_type, target_id, summary, metadata, created_at)
            VALUES
                (:id, :company, :branch, :actor, :actorName, :action, :targetType, :targetId, :summary, :metadata, :createdAt)
            SQL, [
            'id' => $entry->id,
            'company' => $entry->companyId,
            'branch' => $entry->branchId,
            'actor' => $entry->actorId,
            'actorName' => $entry->actorName,
            'action' => $entry->action,
            'targetType' => $entry->targetType,
            'targetId' => $entry->targetId,
            'summary' => $entry->summary,
            'metadata' => json_encode($entry->metadata, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            'createdAt' => $entry->createdAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        ]);
    }
}
