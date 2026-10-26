<?php

declare(strict_types=1);

namespace App\Application\Attendance\Location;

use App\Application\Shared\Bus\Command;

/** Altera só o que vier preenchido (`null` = mantém). O tipo não muda: cadastre outro local. */
final readonly class UpdateLocation implements Command
{
    public function __construct(
        public string $locationId,
        public string $actorId,
        public ?string $label = null,
        public ?bool $active = null,
    ) {
    }
}
