<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Catalog\CategoryId;

final class CategoryIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return CategoryId::class;
    }
}
