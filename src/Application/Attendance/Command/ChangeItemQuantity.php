<?php

declare(strict_types=1);

namespace App\Application\Attendance\Command;

use App\Application\Shared\Bus\Command;

final readonly class ChangeItemQuantity implements Command
{
    public function __construct(
        public string $commandId,
        public string $itemId,
        public string $actorId,
        public int $quantity,
    ) {
    }
}
