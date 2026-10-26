<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CategoryRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 120)] public string $name = '',
        #[Assert\PositiveOrZero] public int $sortOrder = 0,
    ) {
    }
}
