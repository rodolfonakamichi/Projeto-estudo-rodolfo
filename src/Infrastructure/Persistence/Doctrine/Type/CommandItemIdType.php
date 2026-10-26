<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Attendance\CommandItemId;

final class CommandItemIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return CommandItemId::class;
    }
}
