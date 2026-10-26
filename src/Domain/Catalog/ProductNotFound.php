<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\NotFound;

final class ProductNotFound extends NotFound
{
    public static function withId(ProductId $id): self
    {
        return new self("Produto {$id} não encontrado.");
    }
}
