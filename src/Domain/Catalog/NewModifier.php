<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\Money;

/** Dados de um modificador a criar (`Bacon` +R$ 4,00; `Sem cebola` R$ 0). Quem valida é o Product. */
final readonly class NewModifier
{
    public function __construct(
        public ModifierId $id,
        public string $name,
        public Money $priceDelta,
    ) {
    }
}
