<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Event;

use App\Domain\Shared\DomainEvent;

/** Doc 05: comanda cancelada (libera a mesa). */
final readonly class CommandCancelled implements DomainEvent
{
    public function __construct(
        public string $commandId,
        public int $displayNumber,
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
