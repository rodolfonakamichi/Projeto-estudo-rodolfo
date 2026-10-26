<?php

declare(strict_types=1);

namespace App\Application\Audit;

/** Uma linha de `audit_logs` (doc 03 §7): quem fez o quê, em quê, quando. */
final readonly class AuditEntry
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $id,
        public string $companyId,
        public ?string $branchId,
        public ?string $actorId,
        public string $actorName,
        public string $action,
        public string $targetType,
        public string $targetId,
        public string $summary,
        public array $metadata,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
