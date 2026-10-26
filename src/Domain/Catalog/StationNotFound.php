<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\NotFound;

final class StationNotFound extends NotFound
{
    public static function withId(StationId $id): self
    {
        return new self("Estação {$id} não encontrada.");
    }
}
