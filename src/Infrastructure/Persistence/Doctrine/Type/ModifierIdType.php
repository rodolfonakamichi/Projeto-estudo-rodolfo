<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Catalog\ModifierId;

final class ModifierIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return ModifierId::class;
    }
}
