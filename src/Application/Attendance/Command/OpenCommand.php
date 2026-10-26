<?php

declare(strict_types=1);

namespace App\Application\Attendance\Command;

use App\Application\Shared\Bus\Command;

/** Abre uma comanda. `bindType` = MESA (bindRef = ID da mesa), BALCAO, AVULSO, PULSEIRA... */
final readonly class OpenCommand implements Command
{
    public function __construct(
        public string $commandId,
        public string $actorId,
        public string $branchId,
        public string $bindType,
        public ?string $bindRef = null,
    ) {
    }
}
