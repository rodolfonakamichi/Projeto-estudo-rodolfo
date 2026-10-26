<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\Money;

/** Dados de uma variação a criar (`300ml` a R$ 12,00). Quem valida é o Product. */
final readonly class NewVariant
{
    public function __construct(
        public ProductVariantId $id,
        public string $name,
        public Money $price,
        public ?string $sku = null,
    ) {
    }
}
