<?php

declare(strict_types=1);

namespace App\Application\Catalog;

/**
 * Atalhos de lançamento do operador no PWA (doc 07, M2): formato final já
 * agora. `recent` e `topSelling` dependem de vendas — vazios até o M3 passar a
 * calcular a partir das comandas (ranking de verdade no M11).
 */
final readonly class ShortcutsView
{
    /**
     * @param list<ProductView> $favorites
     * @param list<ProductView> $recent
     * @param list<ProductView> $topSelling
     */
    public function __construct(
        public array $favorites,
        public array $recent = [],
        public array $topSelling = [],
    ) {
    }
}
