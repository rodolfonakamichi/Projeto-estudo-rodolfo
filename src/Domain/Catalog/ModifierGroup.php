<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFound;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Grupo de escolha do produto: `Ponto` (escolher exatamente 1), `Adicionais`
 * (de 0 a 5). Obrigatório = `minSelect ≥ 1` (não há flag separada: seria uma
 * segunda fonte da mesma verdade). Parte do agregado Product.
 *
 * Invariante: `0 ≤ min ≤ max`, `max ≥ 1` e o mínimo é alcançável — há pelo
 * menos `min` modificadores ativos (senão ninguém conseguiria pedir o produto).
 */
class ModifierGroup
{
    private const MAX_NAME = 80;

    /** @var Collection<int, Modifier> */
    private Collection $modifiers;

    /** @internal use Product::addModifierGroup() */
    public function __construct(
        private ModifierGroupId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly Product $product,
        private string $name,
        private int $minSelect,
        private int $maxSelect,
        private int $sortOrder,
    ) {
        $this->name = self::assertName($name);
        $this->modifiers = new ArrayCollection();
    }

    public function id(): ModifierGroupId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function minSelect(): int
    {
        return $this->minSelect;
    }

    public function maxSelect(): int
    {
        return $this->maxSelect;
    }

    public function isRequired(): bool
    {
        return $this->minSelect >= 1;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    /** @return list<Modifier> */
    public function modifiers(): array
    {
        return array_values($this->modifiers->toArray());
    }

    /** @return list<Modifier> */
    public function activeModifiers(): array
    {
        return array_values(array_filter($this->modifiers(), static fn (Modifier $modifier): bool => $modifier->isActive()));
    }

    public function modifier(ModifierId $id): Modifier
    {
        foreach ($this->modifiers as $modifier) {
            if ($modifier->id()->equals($id)) {
                return $modifier;
            }
        }

        throw new NotFound("Modificador não encontrado no grupo \"{$this->name}\".");
    }

    /** @internal use Product::addModifier() / addModifierGroup() */
    public function add(NewModifier $new): Modifier
    {
        foreach ($this->modifiers as $modifier) {
            if (CatalogName::same($modifier->name(), $new->name)) {
                throw new DomainException("O grupo \"{$this->name}\" já tem o modificador \"{$modifier->name()}\".");
            }
        }

        $modifier = new Modifier($new->id, $this, $new->name, $new->priceDelta, $this->modifiers->count());
        $this->modifiers->add($modifier);

        return $modifier;
    }

    /** @internal use Product::changeModifierGroupLimits() */
    public function changeLimits(int $minSelect, int $maxSelect): void
    {
        $this->minSelect = $minSelect;
        $this->maxSelect = $maxSelect;
        $this->assertLimits();
    }

    /** @internal use Product::deactivateModifier() */
    public function deactivateModifier(ModifierId $id): void
    {
        $this->modifier($id)->deactivate();
        $this->assertLimits();
    }

    /** Chamada depois de toda mudança no grupo: o Product nunca fica num estado impossível de pedir. */
    public function assertLimits(): void
    {
        if ($this->minSelect < 0 || $this->maxSelect < 1 || $this->minSelect > $this->maxSelect) {
            throw new DomainException("Limites inválidos no grupo \"{$this->name}\": mínimo {$this->minSelect}, máximo {$this->maxSelect} (0 ≤ mínimo ≤ máximo, máximo ≥ 1).");
        }

        $active = \count($this->activeModifiers());
        if ($this->minSelect > $active) {
            throw new DomainException("O grupo \"{$this->name}\" exige {$this->minSelect} escolha(s), mas só tem {$active} opção(ões) ativa(s).");
        }
    }

    private static function assertName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_NAME) {
            throw new DomainException(\sprintf('Nome do grupo deve ter entre 1 e %d caracteres.', self::MAX_NAME));
        }

        return $name;
    }
}
