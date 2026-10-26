<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Attendance\AttendanceQuery;
use App\Application\Attendance\LocationView;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use Doctrine\DBAL\Connection;

/** Read model do atendimento: SQL direto, `company_id` sempre no WHERE. */
final readonly class DbalAttendanceQuery implements AttendanceQuery
{
    public function __construct(private Connection $db)
    {
    }

    public function locations(CompanyId $companyId, BranchId $branchId): array
    {
        $rows = $this->db->fetchAllAssociative(<<<'SQL'
            SELECT id, type, label, is_active
              FROM locations
             WHERE company_id = :company AND branch_id = :branch
             ORDER BY type, label
            SQL, ['company' => $companyId->toString(), 'branch' => $branchId->toString()]);

        return array_map(
            static fn (array $row): LocationView => new LocationView((string) $row['id'], (string) $row['type'], (string) $row['label'], (bool) $row['is_active']),
            $rows,
        );
    }
}
