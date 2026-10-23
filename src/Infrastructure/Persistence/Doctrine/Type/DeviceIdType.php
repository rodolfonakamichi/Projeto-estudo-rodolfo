<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Identity\DeviceId;

final class DeviceIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return DeviceId::class;
    }
}
