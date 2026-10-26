<?php

declare(strict_types=1);

namespace App\Application\Shared\Event;

use App\Domain\Shared\DomainEvent;

/**
 * Um evento de domínio já gravado no outbox, como DADOS (doc 05 §2) — é o que
 * o relay despacha no event.bus. Os consumidores (auditoria, timeline, KDS,
 * estoque) leem `name` + `payload`: não dependem da classe PHP do evento, que
 * pode mudar sem quebrar quem já consome o formato gravado.
 */
final readonly class RecordedEvent implements DomainEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $name,
        public string $aggregateType,
        public string $aggregateId,
        public string $companyId,
        public ?string $branchId,
        public array $payload,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
