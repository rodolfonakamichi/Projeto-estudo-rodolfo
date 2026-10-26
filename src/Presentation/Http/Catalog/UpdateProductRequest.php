<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH: campo ausente = não muda. Em `categoryId`/`stationId`, `null` LIMPA —
 * o controller distingue "ausente" de "null" olhando as chaves do JSON.
 */
final readonly class UpdateProductRequest
{
    public function __construct(
        #[Assert\Length(min: 1, max: 150)] public ?string $name = null,
        public ?bool $trackStock = null,
        public ?bool $active = null,
        #[Assert\Ulid] public ?string $categoryId = null,
        #[Assert\Ulid] public ?string $stationId = null,
    ) {
    }
}
