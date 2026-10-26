<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Application\Shared\Bus\Command;

/**
 * PATCH do produto: `null` = não muda. Categoria e estação podem ser LIMPAS
 * (voltar a null), então "não mexer" e "limpar" precisam de sinais diferentes:
 * os flags `changeCategory`/`changeStation`.
 */
final readonly class UpdateProduct implements Command
{
    public function __construct(
        public string $productId,
        public string $actorId,
        public ?string $name = null,
        public ?bool $trackStock = null,
        public ?bool $active = null,
        public bool $changeCategory = false,
        public ?string $categoryId = null,
        public bool $changeStation = false,
        public ?string $stationId = null,
    ) {
    }
}
