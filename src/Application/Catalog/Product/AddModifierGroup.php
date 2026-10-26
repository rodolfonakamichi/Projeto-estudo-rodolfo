<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Application\Shared\Bus\Command;

final readonly class AddModifierGroup implements Command
{
    public function __construct(
        public string $productId,
        public string $actorId,
        public ModifierGroupInput $group,
    ) {
    }
}
