<?php

declare(strict_types=1);

namespace App\Presentation\Http\Attendance;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LocationRequest
{
    public function __construct(
        #[Assert\NotBlank] public string $type = '',
        #[Assert\NotBlank, Assert\Length(max: 60)] public string $label = '',
    ) {
    }
}
