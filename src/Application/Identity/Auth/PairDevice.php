<?php

declare(strict_types=1);

namespace App\Application\Identity\Auth;

use App\Application\Shared\Bus\Command;

final readonly class PairDevice implements Command
{
    public function __construct(
        public string $deviceId,
        #[\SensitiveParameter] public string $plainToken,
        public string $actingUserId,
        public string $branchId,
        public string $name,
    ) {
    }
}
