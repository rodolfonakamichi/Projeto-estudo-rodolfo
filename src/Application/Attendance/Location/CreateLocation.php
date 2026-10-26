<?php

declare(strict_types=1);

namespace App\Application\Attendance\Location;

use App\Application\Shared\Bus\Command;

/** Cadastra mesa/balcão/área na filial. `type` = TABLE, COUNTER, ROOM, EVENT_AREA. */
final readonly class CreateLocation implements Command
{
    public function __construct(
        public string $locationId,
        public string $actorId,
        public string $branchId,
        public string $type,
        public string $label,
    ) {
    }
}
