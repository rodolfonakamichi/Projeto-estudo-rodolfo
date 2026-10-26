<?php

declare(strict_types=1);

namespace App\Application\Catalog;

/** Produto pronto para o JSON (cardápio do PWA e tela de edição). */
final readonly class ProductView
{
    /**
     * @param list<VariantView>       $variants
     * @param list<ModifierGroupView> $modifierGroups
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $categoryId,
        public ?string $stationId,
        public bool $trackStock,
        public bool $active,
        public array $variants,
        public array $modifierGroups,
    ) {
    }
}
