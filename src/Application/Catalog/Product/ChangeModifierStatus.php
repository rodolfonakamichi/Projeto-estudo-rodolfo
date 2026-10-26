<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Application\Shared\Bus\Command;

/** Liga/desliga um modificador (ex.: acabou o bacon). */
final readonly class ChangeModifierStatus implements Command
{
    public function __construct(
        public string $productId,
        public string $groupId,
        public string $modifierId,
        public string $actorId,
        public bool $active,
    ) {
    }
}
