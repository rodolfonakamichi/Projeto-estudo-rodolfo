<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Identity\UserId;

final class UserIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return UserId::class;
    }
}
