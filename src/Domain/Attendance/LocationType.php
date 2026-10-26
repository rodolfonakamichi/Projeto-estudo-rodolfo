<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Domain\Identity\Capability;

/** Tipo de local físico (doc 03). Alguns só existem com a capability certa ligada. */
enum LocationType: string
{
    case Table = 'TABLE';
    case Counter = 'COUNTER';
    case Room = 'ROOM';
    case EventArea = 'EVENT_AREA';

    /** `null` = qualquer empresa pode ter (balcão, área de evento). */
    public function requiredCapability(): ?Capability
    {
        return match ($this) {
            self::Table => Capability::Tables,
            self::Room => Capability::Hotel,
            self::Counter, self::EventArea => null,
        };
    }
}
