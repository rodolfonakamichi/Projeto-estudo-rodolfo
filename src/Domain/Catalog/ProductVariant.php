<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\Money;

/**
 * Variação vendável do produto (`300ml`, `500ml`; ou a variação padrão de um
 * produto sem tamanho). O PREÇO mora aqui — o item da comanda sempre aponta
 * para uma variação. Parte do agregado Product.
 */
class ProductVariant
{
    private const MAX_NAME = 80;
    private const MAX_SKU = 60;

    private bool $active = true;

    /** @internal use Product::addVariant() */
    public function __construct(
        private ProductVariantId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly Product $product,
        private string $name,
        private Money $price,
        private ?string $sku,
        private int $sortOrder,
    ) {
        $this->name = self::assertName($name);
        $this->price = self::assertPrice($price);
        $this->sku = self::assertSku($sku);
    }

    public function id(): ProductVariantId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function sku(): ?string
    {
        return $this->sku;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** @internal use Product::renameVariant() (checa nome único no produto) */
    public function rename(string $name): void
    {
        $this->name = self::assertName($name);
    }

    /** @internal use Product::changeVariantPrice() */
    public function changePrice(Money $price): void
    {
        $this->price = self::assertPrice($price);
    }

    /** @internal use Product::deactivateVariant() (garante ≥1 ativa) */
    public function deactivate(): void
    {
        $this->active = false;
    }

    /** @internal use Product::activateVariant() */
    public function activate(): void
    {
        $this->active = true;
    }

    private static function assertName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_NAME) {
            throw new DomainException(\sprintf('Nome da variação deve ter entre 1 e %d caracteres.', self::MAX_NAME));
        }

        return $name;
    }

    private static function assertPrice(Money $price): Money
    {
        if ($price->cents() < 0) {
            throw new DomainException('O preço não pode ser negativo.');
        }

        return $price;
    }

    private static function assertSku(?string $sku): ?string
    {
        $sku = null === $sku ? null : trim($sku);
        if ('' === $sku) {
            return null;
        }
        if (null !== $sku && mb_strlen($sku) > self::MAX_SKU) {
            throw new DomainException(\sprintf('SKU deve ter no máximo %d caracteres.', self::MAX_SKU));
        }

        return $sku;
    }
}
