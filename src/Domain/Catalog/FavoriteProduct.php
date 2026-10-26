<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * Um produto fixado na lista de favoritos (PK composta favoritos + produto).
 * Parte do agregado OperatorFavorites.
 *
 * @internal use OperatorFavorites::replace()
 */
class FavoriteProduct
{
    public function __construct(
        private OperatorFavorites $favorites, // parte da PK: sem readonly (proxy do Doctrine)
        private ProductId $productId,         // parte da PK: sem readonly (proxy do Doctrine)
        private int $position,
    ) {
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }
}
