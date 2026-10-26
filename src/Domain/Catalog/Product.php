<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\Money;
use App\Domain\Shared\NotFound;
use App\Domain\Shared\Timestamped;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Produto do cardápio da filial — raiz do agregado (doc 02):
 *   Product ─┬─ ProductVariant[]   (≥1 ativa; o preço mora aqui)
 *            └─ ModifierGroup[] ── Modifier[]   (min/max sempre alcançáveis)
 *
 * Toda mudança nas partes passa por aqui: é o único lugar que enxerga o
 * conjunto e por isso garante as regras que envolvem mais de uma parte
 * ("não desativar a última variação", "nome único no produto").
 *
 * Categoria e estação por ID (outros agregados). Que a categoria/estação são
 * da mesma filial é checado no caso de uso, que tem os repositórios.
 */
class Product implements Timestamped
{
    private const MAX_NAME = 150;

    private ProductKind $kind = ProductKind::Product;
    private bool $trackStock = false;
    private bool $active = true;
    /** @var Collection<int, ProductVariant> */
    private Collection $variants;
    /** @var Collection<int, ModifierGroup> */
    private Collection $modifierGroups;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        private ProductId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly CompanyId $companyId,
        private readonly BranchId $branchId,
        private string $name,
        private ?CategoryId $categoryId,
        private ?StationId $stationId,
    ) {
        $this->variants = new ArrayCollection();
        $this->modifierGroups = new ArrayCollection();
    }

    /**
     * Nasce com ao menos uma variação: sem ela o produto não tem preço.
     * Produto sem tamanho → uma variação só (ex.: nome igual ao do produto).
     *
     * @param list<NewVariant> $variants
     */
    public static function create(
        ProductId $id,
        CompanyId $companyId,
        BranchId $branchId,
        string $name,
        array $variants,
        ?CategoryId $categoryId = null,
        ?StationId $stationId = null,
    ): self {
        if ([] === $variants) {
            throw new DomainException('O produto precisa de ao menos uma variação (é nela que mora o preço).');
        }

        $product = new self($id, $companyId, $branchId, self::assertName($name), $categoryId, $stationId);
        foreach ($variants as $variant) {
            $product->addVariant($variant);
        }

        return $product;
    }

    // ── dados do produto ────────────────────────────────────────────────

    public function rename(string $name): void
    {
        $this->name = self::assertName($name);
    }

    public function moveToCategory(?CategoryId $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    /** Estação padrão de produção (`null` = não vai para o KDS, ex.: refrigerante em lata). */
    public function routeTo(?StationId $stationId): void
    {
        $this->stationId = $stationId;
    }

    public function changeStockTracking(bool $trackStock): void
    {
        $this->trackStock = $trackStock;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    // ── variações ───────────────────────────────────────────────────────

    public function addVariant(NewVariant $new): ProductVariant
    {
        $this->assertVariantNameFree($new->name);

        $variant = new ProductVariant($new->id, $this, $new->name, $new->price, $new->sku, $this->variants->count());
        $this->variants->add($variant);

        return $variant;
    }

    public function renameVariant(ProductVariantId $id, string $name): void
    {
        $this->assertVariantNameFree($name, except: $id);
        $this->variant($id)->rename($name);
    }

    public function changeVariantPrice(ProductVariantId $id, Money $price): void
    {
        $this->variant($id)->changePrice($price);
    }

    public function deactivateVariant(ProductVariantId $id): void
    {
        $variant = $this->variant($id);
        if ($variant->isActive() && 1 === \count($this->activeVariants())) {
            throw new DomainException("\"{$variant->name()}\" é a única variação ativa de \"{$this->name}\": o produto ficaria sem preço.");
        }

        $variant->deactivate();
    }

    public function activateVariant(ProductVariantId $id): void
    {
        $this->variant($id)->activate();
    }

    public function variant(ProductVariantId $id): ProductVariant
    {
        foreach ($this->variants as $variant) {
            if ($variant->id()->equals($id)) {
                return $variant;
            }
        }

        throw new NotFound("Variação não encontrada no produto \"{$this->name}\".");
    }

    // ── grupos de modificadores ─────────────────────────────────────────

    /** @param list<NewModifier> $modifiers */
    public function addModifierGroup(ModifierGroupId $id, string $name, int $minSelect, int $maxSelect, array $modifiers): ModifierGroup
    {
        foreach ($this->modifierGroups as $group) {
            if (CatalogName::same($group->name(), $name)) {
                throw new DomainException("O produto já tem o grupo \"{$group->name()}\".");
            }
        }

        $group = new ModifierGroup($id, $this, $name, $minSelect, $maxSelect, $this->modifierGroups->count());
        foreach ($modifiers as $modifier) {
            $group->add($modifier);
        }
        $group->assertLimits();

        $this->modifierGroups->add($group);

        return $group;
    }

    public function changeModifierGroupLimits(ModifierGroupId $id, int $minSelect, int $maxSelect): void
    {
        $this->modifierGroup($id)->changeLimits($minSelect, $maxSelect);
    }

    public function addModifier(ModifierGroupId $groupId, NewModifier $modifier): Modifier
    {
        return $this->modifierGroup($groupId)->add($modifier);
    }

    public function deactivateModifier(ModifierGroupId $groupId, ModifierId $modifierId): void
    {
        $this->modifierGroup($groupId)->deactivateModifier($modifierId);
    }

    public function activateModifier(ModifierGroupId $groupId, ModifierId $modifierId): void
    {
        $this->modifierGroup($groupId)->modifier($modifierId)->activate();
    }

    public function modifierGroup(ModifierGroupId $id): ModifierGroup
    {
        foreach ($this->modifierGroups as $group) {
            if ($group->id()->equals($id)) {
                return $group;
            }
        }

        throw new NotFound("Grupo de modificadores não encontrado no produto \"{$this->name}\".");
    }

    // ── leitura ─────────────────────────────────────────────────────────

    public function id(): ProductId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function branchId(): BranchId
    {
        return $this->branchId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function kind(): ProductKind
    {
        return $this->kind;
    }

    public function categoryId(): ?CategoryId
    {
        return $this->categoryId;
    }

    public function stationId(): ?StationId
    {
        return $this->stationId;
    }

    public function tracksStock(): bool
    {
        return $this->trackStock;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** @return list<ProductVariant> */
    public function variants(): array
    {
        return array_values($this->variants->toArray());
    }

    /** @return list<ProductVariant> */
    public function activeVariants(): array
    {
        return array_values(array_filter($this->variants(), static fn (ProductVariant $variant): bool => $variant->isActive()));
    }

    /** @return list<ModifierGroup> */
    public function modifierGroups(): array
    {
        return array_values($this->modifierGroups->toArray());
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    private function assertVariantNameFree(string $name, ?ProductVariantId $except = null): void
    {
        foreach ($this->variants as $variant) {
            if (null !== $except && $variant->id()->equals($except)) {
                continue;
            }
            if (CatalogName::same($variant->name(), $name)) {
                throw new DomainException("O produto já tem a variação \"{$variant->name()}\".");
            }
        }
    }

    private static function assertName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_NAME) {
            throw new DomainException(\sprintf('Nome do produto deve ter entre 1 e %d caracteres.', self::MAX_NAME));
        }

        return $name;
    }
}
