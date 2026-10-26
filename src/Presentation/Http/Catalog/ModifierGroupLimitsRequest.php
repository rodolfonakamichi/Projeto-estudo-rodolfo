<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

final readonly class ModifierGroupLimitsRequest
{
    public function __construct(
        public int $minSelect = 0,
        public int $maxSelect = 1,
    ) {
    }
}
