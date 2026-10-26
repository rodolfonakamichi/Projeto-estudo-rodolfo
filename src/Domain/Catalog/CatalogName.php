<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/** Regra de comparação de nomes dentro do produto: sem diferenciar maiúsculas nem espaços nas pontas. */
final class CatalogName
{
    public static function same(string $first, string $second): bool
    {
        return mb_strtolower(trim($first)) === mb_strtolower(trim($second));
    }
}
