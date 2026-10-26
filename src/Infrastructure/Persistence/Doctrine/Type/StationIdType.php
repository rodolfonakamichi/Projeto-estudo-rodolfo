<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Catalog\StationId;

final class StationIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return StationId::class;
    }
}
