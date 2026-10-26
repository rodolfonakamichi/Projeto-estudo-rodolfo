<?php

declare(strict_types=1);

namespace App\Application\Attendance;

final readonly class CommandItemView
{
    /** @param list<array{modifierId: string, name: string, priceDeltaCents: int}> $modifiers */
    public function __construct(
        public string $id,
        public string $productId,
        public string $variantId,
        public string $name,
        public int $quantity,
        public int $unitPriceCents,
        public int $modifiersTotalCents,
        public int $lineTotalCents,
        public ?string $notes,
        public string $status,
        public ?string $cancelReason,
        public array $modifiers,
    ) {
    }
}
