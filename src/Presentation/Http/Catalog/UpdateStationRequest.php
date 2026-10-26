<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

/** PATCH: campo ausente = não muda. */
final readonly class UpdateStationRequest
{
    public function __construct(
        #[Assert\Length(min: 1, max: 60)] public ?string $name = null,
        public ?bool $active = null,
    ) {
    }
}
