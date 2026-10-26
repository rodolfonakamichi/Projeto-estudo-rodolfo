<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Domain\Catalog\NewVariant;
use App\Domain\Catalog\ProductVariantId;
use App\Domain\Shared\Money;

/** Variação vinda de fora (dinheiro sempre em centavos inteiros, nunca float). */
final readonly class VariantInput
{
    public function __construct(
        public string $id,
        public string $name,
        public int $priceCents,
        public ?string $sku = null,
    ) {
    }

    public function toNewVariant(): NewVariant
    {
        return new NewVariant(ProductVariantId::fromString($this->id), $this->name, Money::fromCents($this->priceCents), $this->sku);
    }
}
