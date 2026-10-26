<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

/** PATCH: campo ausente = não muda. */
final readonly class UpdateVariantRequest
{
    public function __construct(
        #[Assert\Length(min: 1, max: 80)] public ?string $name = null,
        #[Assert\PositiveOrZero] public ?int $priceCents = null,
        public ?bool $active = null,
    ) {
    }
}
