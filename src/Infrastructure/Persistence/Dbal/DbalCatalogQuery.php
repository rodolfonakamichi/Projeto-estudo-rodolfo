<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Catalog\CatalogQuery;
use App\Application\Catalog\CategoryView;
use App\Application\Catalog\ModifierGroupView;
use App\Application\Catalog\ModifierView;
use App\Application\Catalog\ProductView;
use App\Application\Catalog\StationView;
use App\Application\Catalog\VariantView;
use App\Domain\Catalog\CategoryId;
use App\Domain\Catalog\ProductId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Read model do catálogo: SQL direto, `company_id` sempre no WHERE (o filtro do
 * ORM não chega aqui). Produtos em lote: 4 consultas no total (produtos,
 * variações, grupos, modificadores) com `IN (...)`, não uma por produto (N+1).
 */
final readonly class DbalCatalogQuery implements CatalogQuery
{
    public function __construct(private Connection $db)
    {
    }

    public function stations(CompanyId $companyId, BranchId $branchId): array
    {
        $rows = $this->db->fetchAllAssociative(<<<'SQL'
            SELECT id, name, is_active
              FROM stations
             WHERE company_id = :company AND branch_id = :branch
             ORDER BY name
            SQL, ['company' => $companyId->toString(), 'branch' => $branchId->toString()]);

        return array_map(
            static fn (array $row): StationView => new StationView((string) $row['id'], (string) $row['name'], (bool) $row['is_active']),
            $rows,
        );
    }

    public function categories(CompanyId $companyId, BranchId $branchId): array
    {
        $rows = $this->db->fetchAllAssociative(<<<'SQL'
            SELECT id, name, sort_order
              FROM categories
             WHERE company_id = :company AND branch_id = :branch AND archived_at IS NULL
             ORDER BY sort_order, name
            SQL, ['company' => $companyId->toString(), 'branch' => $branchId->toString()]);

        return array_map(
            static fn (array $row): CategoryView => new CategoryView((string) $row['id'], (string) $row['name'], (int) $row['sort_order']),
            $rows,
        );
    }

    public function products(CompanyId $companyId, BranchId $branchId, ?CategoryId $categoryId = null): array
    {
        $sql = 'SELECT id, name, category_id, station_id, track_stock, is_active FROM products WHERE company_id = :company AND branch_id = :branch';
        $parameters = ['company' => $companyId->toString(), 'branch' => $branchId->toString()];
        if (null !== $categoryId) {
            $sql .= ' AND category_id = :category';
            $parameters['category'] = $categoryId->toString();
        }

        return $this->assemble($this->db->fetchAllAssociative($sql.' ORDER BY name', $parameters));
    }

    public function product(CompanyId $companyId, ProductId $productId): ?ProductView
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, name, category_id, station_id, track_stock, is_active FROM products WHERE company_id = :company AND id = :product',
            ['company' => $companyId->toString(), 'product' => $productId->toString()],
        );

        return $this->assemble($rows)[0] ?? null;
    }

    public function search(CompanyId $companyId, BranchId $branchId, string $text, int $limit = 20): array
    {
        $terms = FulltextQuery::fromUserInput($text);
        if (null === $terms) {
            return [];
        }

        // MATCH no WHERE usa o índice ft_products_name; o mesmo MATCH no ORDER BY é a relevância
        $rows = $this->db->fetchAllAssociative(<<<'SQL'
            SELECT id, name, category_id, station_id, track_stock, is_active
              FROM products
             WHERE company_id = :company AND branch_id = :branch AND is_active = 1
               AND MATCH (name) AGAINST (:terms IN BOOLEAN MODE)
             ORDER BY MATCH (name) AGAINST (:terms IN BOOLEAN MODE) DESC, name
             LIMIT :limit
            SQL,
            ['company' => $companyId->toString(), 'branch' => $branchId->toString(), 'terms' => $terms, 'limit' => $limit],
            ['limit' => ParameterType::INTEGER],
        );

        return $this->assemble($rows);
    }

    /**
     * @param list<array<string, mixed>> $productRows
     *
     * @return list<ProductView>
     */
    private function assemble(array $productRows): array
    {
        if ([] === $productRows) {
            return [];
        }

        $productIds = array_map(static fn (array $row): string => (string) $row['id'], $productRows);
        $variants = $this->groupedBy('product_id', $this->db->fetchAllAssociative(
            'SELECT id, product_id, name, price_cents, sku, is_active FROM product_variants WHERE product_id IN (:ids) ORDER BY sort_order',
            ['ids' => $productIds],
            ['ids' => ArrayParameterType::STRING],
        ));
        $groupRows = $this->db->fetchAllAssociative(
            'SELECT id, product_id, name, min_select, max_select FROM modifier_groups WHERE product_id IN (:ids) ORDER BY sort_order',
            ['ids' => $productIds],
            ['ids' => ArrayParameterType::STRING],
        );
        $modifiers = [] === $groupRows ? [] : $this->groupedBy('modifier_group_id', $this->db->fetchAllAssociative(
            'SELECT id, modifier_group_id, name, price_delta_cents, is_active FROM modifiers WHERE modifier_group_id IN (:ids) ORDER BY sort_order',
            ['ids' => array_map(static fn (array $row): string => (string) $row['id'], $groupRows)],
            ['ids' => ArrayParameterType::STRING],
        ));
        $groups = $this->groupedBy('product_id', $groupRows);

        return array_map(static fn (array $row): ProductView => new ProductView(
            (string) $row['id'],
            (string) $row['name'],
            null === $row['category_id'] ? null : (string) $row['category_id'],
            null === $row['station_id'] ? null : (string) $row['station_id'],
            (bool) $row['track_stock'],
            (bool) $row['is_active'],
            array_map(static fn (array $variant): VariantView => new VariantView(
                (string) $variant['id'],
                (string) $variant['name'],
                (int) $variant['price_cents'],
                null === $variant['sku'] ? null : (string) $variant['sku'],
                (bool) $variant['is_active'],
            ), $variants[$row['id']] ?? []),
            array_map(static fn (array $group): ModifierGroupView => new ModifierGroupView(
                (string) $group['id'],
                (string) $group['name'],
                (int) $group['min_select'],
                (int) $group['max_select'],
                (int) $group['min_select'] >= 1,
                array_map(static fn (array $modifier): ModifierView => new ModifierView(
                    (string) $modifier['id'],
                    (string) $modifier['name'],
                    (int) $modifier['price_delta_cents'],
                    (bool) $modifier['is_active'],
                ), $modifiers[$group['id']] ?? []),
            ), $groups[$row['id']] ?? []),
        ), $productRows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupedBy(string $column, array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row[$column]][] = $row;
        }

        return $grouped;
    }
}
