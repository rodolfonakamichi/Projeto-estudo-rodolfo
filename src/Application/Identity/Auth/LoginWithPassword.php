<?php

declare(strict_types=1);

namespace App\Application\Identity\Auth;

use App\Application\Shared\Bus\Command;

/** `plainToken`: gerado por quem chama (OpaqueToken::generate) — o handler só grava o hash. */
final readonly class LoginWithPassword implements Command
{
    public function __construct(
        public string $tokenId,
        #[\SensitiveParameter] public string $plainToken,
        public string $companySlug,
        public string $email,
        #[\SensitiveParameter] public string $password,
        public string $tokenName = 'login',
    ) {
    }
}
