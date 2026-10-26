<?php

declare(strict_types=1);

namespace App\Application\Identity\Auth;

final readonly class DeviceUser
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
