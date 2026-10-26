<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\Money;

/**
 * "O que o cliente escolheu", já validado pelo Product e com os preços de
 * agora: é a linguagem publicada do Catálogo para o Atendimento (doc 01,
 * Customer/Supplier). A comanda COPIA estes valores — se o cardápio mudar
 * amanhã, o item lançado hoje não muda.
 */
final readonly class ProductSelection
{
    /** @param list<SelectedModifier> $modifiers */
    public function __construct(
        public ProductId $productId,
        public ProductVariantId $variantId,
        public string $productName,
        public string $variantName,
        public Money $unitPrice,
        public array $modifiers,
        public ?StationId $stationId,
    ) {
    }

    /** Soma dos deltas dos modificadores (pode ser negativa: "Sem pão"). */
    public function modifiersTotal(): Money
    {
        return array_reduce(
            $this->modifiers,
            static fn (Money $total, SelectedModifier $modifier): Money => $total->add($modifier->priceDelta),
            Money::fromCents(0, $this->unitPrice->currency()),
        );
    }
}
