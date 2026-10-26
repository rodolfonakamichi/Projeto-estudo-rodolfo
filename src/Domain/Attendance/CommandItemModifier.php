<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Domain\Catalog\ModifierId;
use App\Domain\Shared\Money;

/**
 * Modificador escolhido no item, COPIADO do cardápio (nome e preço do momento).
 * PK composta (item, modificador). Parte do agregado Command.
 *
 * @internal use Command::addItem()
 */
class CommandItemModifier
{
    public function __construct(
        private CommandItem $item,          // parte da PK: sem readonly (proxy do Doctrine)
        private ModifierId $modifierId,     // parte da PK: sem readonly (proxy do Doctrine)
        private readonly string $name,
        private readonly Money $priceDelta,
    ) {
    }

    public function modifierId(): ModifierId
    {
        return $this->modifierId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function priceDelta(): Money
    {
        return $this->priceDelta;
    }
}
