<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Catalog\ProductVariantId;

final class ProductVariantIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return ProductVariantId::class;
    }
}
