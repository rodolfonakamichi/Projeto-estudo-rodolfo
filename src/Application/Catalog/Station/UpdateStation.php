<?php

declare(strict_types=1);

namespace App\Application\Catalog\Station;

use App\Application\Shared\Bus\Command;

/** Altera só o que vier preenchido (`null` = mantém). */
final readonly class UpdateStation implements Command
{
    public function __construct(
        public string $stationId,
        public string $actorId,
        public ?string $name = null,
        public ?bool $active = null,
    ) {
    }
}
