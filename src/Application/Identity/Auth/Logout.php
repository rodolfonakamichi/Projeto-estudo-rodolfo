<?php

declare(strict_types=1);

namespace App\Application\Identity\Auth;

use App\Application\Shared\Bus\Command;

final readonly class Logout implements Command
{
    public function __construct(#[\SensitiveParameter] public string $plainToken)
    {
    }
}
