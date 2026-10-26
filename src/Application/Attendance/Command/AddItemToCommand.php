<?php

declare(strict_types=1);

namespace App\Application\Attendance\Command;

use App\Application\Shared\Bus\Command;

/** Lança um item: variação + quantidade + modificadores escolhidos (+ observação). */
final readonly class AddItemToCommand implements Command
{
    /** @param list<string> $modifierIds */
    public function __construct(
        public string $commandId,
        public string $itemId,
        public string $actorId,
        public string $productId,
        public string $variantId,
        public int $quantity = 1,
        public array $modifierIds = [],
        public ?string $notes = null,
    ) {
    }
}
