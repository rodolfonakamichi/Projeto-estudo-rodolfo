<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Attendance\CommandId;

final class CommandIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return CommandId::class;
    }
}
