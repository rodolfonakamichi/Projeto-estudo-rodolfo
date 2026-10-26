<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Catalog\CatalogQuery;
use App\Application\Catalog\CategoryView;
use App\Application\Catalog\StationView;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use Doctrine\DBAL\Connection;

/** Read model do catálogo: SQL direto, `company_id` sempre no WHERE (o filtro do ORM não chega aqui). */
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
}
