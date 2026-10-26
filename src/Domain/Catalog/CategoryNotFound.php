<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\NotFound;

final class CategoryNotFound extends NotFound
{
    public static function withId(CategoryId $id): self
    {
        return new self("Categoria {$id} não encontrada.");
    }
}
