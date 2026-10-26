<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Domain\Shared\NotFound;

final class LocationNotFound extends NotFound
{
    public static function withId(LocationId $id): self
    {
        return new self("Local {$id} não encontrado.");
    }
}
