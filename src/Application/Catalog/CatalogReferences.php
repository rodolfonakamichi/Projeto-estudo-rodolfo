<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\CategoryId;
use App\Domain\Catalog\CategoryNotFound;
use App\Domain\Catalog\CategoryRepository;
use App\Domain\Catalog\StationId;
use App\Domain\Catalog\StationNotFound;
use App\Domain\Catalog\StationRepository;
use App\Domain\Identity\BranchId;
use App\Domain\Shared\DomainException;

/**
 * Confere as referências por ID do produto: o Product guarda só CategoryId e
 * StationId e não tem como saber se elas existem. Aqui (com os repositórios)
 * garante: mesma filial do produto, categoria não arquivada, estação ativa.
 * Categoria/estação de outra filial = "não encontrada" (404).
 */
final readonly class CatalogReferences
{
    public function __construct(
        private CategoryRepository $categories,
        private StationRepository $stations,
    ) {
    }

    public function category(?string $categoryId, BranchId $branchId): ?CategoryId
    {
        if (null === $categoryId) {
            return null;
        }

        $id = CategoryId::fromString($categoryId);
        $category = $this->categories->get($id);
        if (!$category->branchId()->equals($branchId)) {
            throw CategoryNotFound::withId($id);
        }
        if ($category->isArchived()) {
            throw new DomainException("A categoria \"{$category->name()}\" está arquivada.");
        }

        return $id;
    }

    public function station(?string $stationId, BranchId $branchId): ?StationId
    {
        if (null === $stationId) {
            return null;
        }

        $id = StationId::fromString($stationId);
        $station = $this->stations->get($id);
        if (!$station->branchId()->equals($branchId)) {
            throw StationNotFound::withId($id);
        }
        if (!$station->isActive()) {
            throw new DomainException("A estação \"{$station->name()}\" está inativa.");
        }

        return $id;
    }
}
