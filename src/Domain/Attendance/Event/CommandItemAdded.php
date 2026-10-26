<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Event;

use App\Domain\Shared\DomainEvent;

/** Doc 05: item lançado (com preço copiado). Payload autocontido: quem consome não precisa consultar nada. */
final readonly class CommandItemAdded implements DomainEvent
{
    /** @param list<array{modifierId: string, name: string, priceDeltaCents: int}> $modifiers */
    public function __construct(
        public string $commandId,
        public int $displayNumber,
        public string $itemId,
        public string $productId,
        public string $variantId,
        public string $itemName,
        public int $quantity,
        public int $unitPriceCents,
        public array $modifiers,
        public int $lineTotalCents,
        public string $actorId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
