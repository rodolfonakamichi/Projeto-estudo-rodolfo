<?php

declare(strict_types=1);

namespace App\Application\Catalog\Favorites;

use App\Application\Shared\Bus\Command;

/** Troca a lista de favoritos do ator nesta filial (na ordem dada; vazia = limpa). */
final readonly class SetFavoriteProducts implements Command
{
    /** @param list<string> $productIds */
    public function __construct(
        public string $favoritesId,
        public string $actorId,
        public string $branchId,
        public array $productIds,
    ) {
    }
}
