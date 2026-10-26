<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Catalog\OperatorFavoritesId;

final class OperatorFavoritesIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return OperatorFavoritesId::class;
    }
}
