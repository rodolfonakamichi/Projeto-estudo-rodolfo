<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Domain\Shared\NotFound;

final class CommandNotFound extends NotFound
{
    public static function withId(CommandId $id): self
    {
        return new self("Comanda {$id} não encontrada.");
    }
}
