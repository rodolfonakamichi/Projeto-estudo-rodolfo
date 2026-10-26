<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Application\Shared\Bus\Command;

/** Cria o produto completo de uma vez: variações (≥1) e grupos de modificadores. */
final readonly class CreateProduct implements Command
{
    /**
     * @param list<VariantInput>       $variants
     * @param list<ModifierGroupInput> $modifierGroups
     */
    public function __construct(
        public string $productId,
        public string $actorId,
        public string $branchId,
        public string $name,
        public array $variants,
        public array $modifierGroups = [],
        public ?string $categoryId = null,
        public ?string $stationId = null,
        public bool $trackStock = false,
    ) {
    }
}
