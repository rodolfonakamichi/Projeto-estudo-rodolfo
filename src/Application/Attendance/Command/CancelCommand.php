<?php

declare(strict_types=1);

namespace App\Application\Attendance\Command;

use App\Application\Shared\Bus\Command;

/** Cancela a comanda inteira (aberta por engano, cliente foi embora sem consumir). Libera a mesa. */
final readonly class CancelCommand implements Command
{
    public function __construct(
        public string $commandId,
        public string $actorId,
        public string $reason,
    ) {
    }
}
