<?php

declare(strict_types=1);

namespace App\Application\Catalog;

final readonly class VariantView
{
    public function __construct(
        public string $id,
        public string $name,
        public int $priceCents,
        public ?string $sku,
        public bool $active,
    ) {
    }
}
