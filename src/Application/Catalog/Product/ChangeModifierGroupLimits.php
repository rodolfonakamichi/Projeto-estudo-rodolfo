<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Application\Shared\Bus\Command;

/** Mínimo e máximo vão juntos: validar um sem o outro deixaria passar estado incoerente. */
final readonly class ChangeModifierGroupLimits implements Command
{
    public function __construct(
        public string $productId,
        public string $groupId,
        public string $actorId,
        public int $minSelect,
        public int $maxSelect,
    ) {
    }
}
