<?php

declare(strict_types=1);

namespace App\Presentation\Http\Attendance;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ItemQuantityRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: 999)] public int $quantity = 1,
    ) {
    }
}
