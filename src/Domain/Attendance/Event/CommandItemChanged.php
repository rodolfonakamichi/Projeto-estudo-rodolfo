<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Event;

use App\Domain\Shared\DomainEvent;

/** Doc 05: quantidade alterada (antes → depois). */
final readonly class CommandItemChanged implements DomainEvent
{
    public function __construct(
        public string $commandId,
        public int $displayNumber,
        public string $itemId,
        public string $itemName,
        public int $oldQuantity,
        public int $newQuantity,
        public string $actorId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
