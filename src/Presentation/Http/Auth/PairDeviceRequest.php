<?php

declare(strict_types=1);

namespace App\Presentation\Http\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PairDeviceRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Ulid] public string $branchId = '',
        #[Assert\NotBlank, Assert\Length(max: 80)] public string $name = '',
    ) {
    }
}
