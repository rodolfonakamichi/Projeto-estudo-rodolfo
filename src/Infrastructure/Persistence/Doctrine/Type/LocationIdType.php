<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Attendance\LocationId;

final class LocationIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return LocationId::class;
    }
}
