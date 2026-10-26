<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

/** Dinheiro em centavos inteiros: `priceCents: 1200` = R$ 12,00 (JSON nunca leva float de dinheiro). */
final readonly class VariantRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 80)] public string $name = '',
        #[Assert\PositiveOrZero] public int $priceCents = 0,
        #[Assert\Length(max: 60)] public ?string $sku = null,
    ) {
    }
}
