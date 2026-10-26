<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Identity\RoleId;

final class RoleIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return RoleId::class;
    }
}
