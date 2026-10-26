<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class StationRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 60)] public string $name = '',
    ) {
    }
}
