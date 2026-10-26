<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\CategoryId;
use App\Domain\Catalog\ProductId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;

/**
 * Leitura do catálogo (lado "Query" do CQRS): SQL direto, devolve DTOs prontos
 * para o JSON, sem hidratar agregados. O `companyId` é obrigatório porque SQL
 * puro não passa pelo CompanyFilter (ADR-004).
 */
interface CatalogQuery
{
    /** @return list<StationView> ativas e inativas, por nome */
    public function stations(CompanyId $companyId, BranchId $branchId): array;

    /** @return list<CategoryView> só as não arquivadas, na ordem do cardápio */
    public function categories(CompanyId $companyId, BranchId $branchId): array;

    /** @return list<ProductView> ativos e inativos, por nome; opcionalmente de uma categoria */
    public function products(CompanyId $companyId, BranchId $branchId, ?CategoryId $categoryId = null): array;

    public function product(CompanyId $companyId, ProductId $productId): ?ProductView;

    /**
     * Busca rápida por nome (lançamento no PWA): só produtos ativos, mais
     * relevantes primeiro. Texto sem palavra pesquisável → lista vazia.
     *
     * @return list<ProductView>
     */
    public function search(CompanyId $companyId, BranchId $branchId, string $text, int $limit = 20): array;
}
