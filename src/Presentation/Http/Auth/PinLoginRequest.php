<?php

declare(strict_types=1);

namespace App\Presentation\Http\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PinLoginRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Ulid] public string $userId = '',
        #[Assert\NotBlank, Assert\Regex('/^\d{4,6}$/')] #[\SensitiveParameter] public string $pin = '',
    ) {
    }
}
