<?php

declare(strict_types=1);

namespace App\Application\Audit;

/** Porta: grava na auditoria. Idempotente pelo `id` (o mesmo evento reprocessado não duplica). */
interface AuditLog
{
    public function record(AuditEntry $entry): void;
}
