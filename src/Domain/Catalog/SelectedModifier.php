<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\Money;

/** Modificador escolhido, com nome e preço DO MOMENTO (a comanda copia). */
final readonly class SelectedModifier
{
    public function __construct(
        public ModifierId $modifierId,
        public string $name,
        public Money $priceDelta,
    ) {
    }
}
