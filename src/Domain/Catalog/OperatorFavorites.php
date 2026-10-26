<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\Timestamped;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Favoritos de UM operador numa filial: os produtos que ele fixou para lançar
 * com um toque no PWA (doc 07, M2). Cada pessoa tem a sua lista por filial;
 * ninguém mexe na do outro.
 *
 * A lista é trocada inteira (`replace`): é como o PWA trabalha — arrasta,
 * reordena e manda a lista final.
 */
class OperatorFavorites implements Timestamped
{
    public const MAX = 30;

    /** @var Collection<int, FavoriteProduct> */
    private Collection $items;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        private OperatorFavoritesId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly CompanyId $companyId,
        private readonly BranchId $branchId,
        private readonly UserId $userId,
    ) {
        $this->items = new ArrayCollection();
    }

    public static function start(OperatorFavoritesId $id, CompanyId $companyId, BranchId $branchId, UserId $userId): self
    {
        return new self($id, $companyId, $branchId, $userId);
    }

    /**
     * Troca a lista pela nova, na ordem dada. Compara em vez de apagar tudo e
     * recriar: apagar e reinserir o mesmo produto no mesmo flush faria o
     * Doctrine inserir antes de apagar e estourar a chave primária.
     *
     * @param list<ProductId> $productIds
     */
    public function replace(array $productIds): void
    {
        if (\count($productIds) > self::MAX) {
            throw new DomainException(\sprintf('No máximo %d favoritos.', self::MAX));
        }

        $positions = [];
        foreach ($productIds as $position => $productId) {
            if (isset($positions[$productId->toString()])) {
                throw new DomainException('Produto repetido nos favoritos.');
            }
            $positions[$productId->toString()] = $position;
        }

        foreach ($this->items->toArray() as $item) {
            $key = $item->productId()->toString();
            if (isset($positions[$key])) {
                $item->moveTo($positions[$key]);
                unset($positions[$key]);
            } else {
                $this->items->removeElement($item);
            }
        }

        foreach ($productIds as $position => $productId) {
            if (isset($positions[$productId->toString()])) {
                $this->items->add(new FavoriteProduct($this, $productId, $position));
            }
        }
    }

    /** @return list<ProductId> na ordem escolhida */
    public function productIds(): array
    {
        $items = $this->items->toArray();
        usort($items, static fn (FavoriteProduct $first, FavoriteProduct $second): int => $first->position() <=> $second->position());

        return array_map(static fn (FavoriteProduct $item): ProductId => $item->productId(), $items);
    }

    public function id(): OperatorFavoritesId
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

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }
}
