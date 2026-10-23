<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Identity\UserRoleId;

final class UserRoleIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return UserRoleId::class;
    }
}
