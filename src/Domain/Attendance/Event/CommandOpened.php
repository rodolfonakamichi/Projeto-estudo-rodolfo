<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Event;

use App\Domain\Shared\DomainEvent;

/** Doc 05: comanda aberta. Payload em tipos simples: vai para o outbox (JSON) no M3.3. */
final readonly class CommandOpened implements DomainEvent
{
    public function __construct(
        public string $commandId,
        public int $displayNumber,
        public string $bindType,
        public ?string $bindRef,
        public string $openedBy,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
