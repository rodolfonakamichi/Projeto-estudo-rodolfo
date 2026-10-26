<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

/** Item nunca some (doc 02, invariante 5): cancelado continua lá, com motivo e autor. */
enum CommandItemStatus: string
{
    case Active = 'ACTIVE';
    case Cancelled = 'CANCELLED';
}
