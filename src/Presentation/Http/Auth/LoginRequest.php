<?php

declare(strict_types=1);

namespace App\Presentation\Http\Auth;

use Symfony\Component\Validator\Constraints as Assert;

/** Corpo de POST /api/auth/login — validado pelo #[MapRequestPayload] (422 se inválido). */
final readonly class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank] public string $company = '',
        #[Assert\NotBlank, Assert\Email] public string $email = '',
        #[Assert\NotBlank] #[\SensitiveParameter] public string $password = '',
    ) {
    }
}
