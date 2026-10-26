<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

final readonly class ModifierStatusRequest
{
    public function __construct(
        public bool $active = true,
    ) {
    }
}
