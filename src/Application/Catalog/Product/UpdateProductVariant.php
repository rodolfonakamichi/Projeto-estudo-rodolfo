<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Application\Shared\Bus\Command;

/** PATCH da variação: `null` = não muda. Mudar preço exige `catalog.price.manage`. */
final readonly class UpdateProductVariant implements Command
{
    public function __construct(
        public string $productId,
        public string $variantId,
        public string $actorId,
        public ?string $name = null,
        public ?int $priceCents = null,
        public ?bool $active = null,
    ) {
    }
}
