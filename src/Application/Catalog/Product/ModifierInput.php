<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Domain\Catalog\ModifierId;
use App\Domain\Catalog\NewModifier;
use App\Domain\Shared\Money;

final readonly class ModifierInput
{
    public function __construct(
        public string $id,
        public string $name,
        public int $priceDeltaCents,
    ) {
    }

    public function toNewModifier(): NewModifier
    {
        return new NewModifier(ModifierId::fromString($this->id), $this->name, Money::fromCents($this->priceDeltaCents));
    }
}
