<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Catalog\ProductId;

final class ProductIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return ProductId::class;
    }
}
