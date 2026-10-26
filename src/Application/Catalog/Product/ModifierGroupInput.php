<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Domain\Catalog\ModifierGroupId;
use App\Domain\Catalog\NewModifier;
use App\Domain\Catalog\Product;

final readonly class ModifierGroupInput
{
    /** @param list<ModifierInput> $modifiers */
    public function __construct(
        public string $id,
        public string $name,
        public int $minSelect,
        public int $maxSelect,
        public array $modifiers = [],
    ) {
    }

    public function addTo(Product $product): void
    {
        $product->addModifierGroup(
            ModifierGroupId::fromString($this->id),
            $this->name,
            $this->minSelect,
            $this->maxSelect,
            array_map(static fn (ModifierInput $modifier): NewModifier => $modifier->toNewModifier(), $this->modifiers),
        );
    }
}
