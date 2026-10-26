<?php

declare(strict_types=1);

namespace App\Application\Catalog\Station;

use App\Application\Shared\Bus\Command;

/** Cria uma estação de produção na filial (exige a capability `kitchen`). */
final readonly class CreateStation implements Command
{
    public function __construct(
        public string $stationId,
        public string $actorId,
        public string $branchId,
        public string $name,
    ) {
    }
}
