<?php

declare(strict_types=1);

namespace App\Application\Identity\Auth;

use App\Application\Shared\Bus\Command;

final readonly class LoginWithPin implements Command
{
    public function __construct(
        public string $tokenId,
        #[\SensitiveParameter] public string $plainToken,
        public string $deviceId,
        public string $userId,
        #[\SensitiveParameter] public string $pin,
    ) {
    }
}
