<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\Money;

/**
 * Opção de um grupo (`Bacon` +R$ 4,00, `Ao ponto` R$ 0, `Sem pão` −R$ 2,00).
 * O delta pode ser zero ou negativo. Parte do agregado Product.
 */
class Modifier
{
    private const MAX_NAME = 80;

    private bool $active = true;

    /** @internal use Product::addModifier() / addModifierGroup() */
    public function __construct(
        private ModifierId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly ModifierGroup $group,
        private string $name,
        private Money $priceDelta,
        private int $sortOrder,
    ) {
        $this->name = self::assertName($name);
    }

    public function id(): ModifierId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function priceDelta(): Money
    {
        return $this->priceDelta;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** @internal use Product::deactivateModifier() (garante o mínimo do grupo) */
    public function deactivate(): void
    {
        $this->active = false;
    }

    /** @internal use Product::activateModifier() */
    public function activate(): void
    {
        $this->active = true;
    }

    private static function assertName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_NAME) {
            throw new DomainException(\sprintf('Nome do modificador deve ter entre 1 e %d caracteres.', self::MAX_NAME));
        }

        return $name;
    }
}
