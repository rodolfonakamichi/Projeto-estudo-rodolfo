<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Event;

use App\Domain\Shared\DomainEvent;

/** Doc 05: item cancelado — com motivo e autor (vai para auditoria no M3.3). */
final readonly class CommandItemCancelled implements DomainEvent
{
    public function __construct(
        public string $commandId,
        public string $itemId,
        public string $reason,
        public string $actorId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
