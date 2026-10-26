<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Catalog\ModifierGroupId;

final class ModifierGroupIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return ModifierGroupId::class;
    }
}
