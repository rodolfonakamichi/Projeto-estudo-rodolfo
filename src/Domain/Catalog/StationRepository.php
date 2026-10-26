<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Identity\BranchId;

interface StationRepository
{
    public function save(Station $station): void;

    /** @throws StationNotFound */
    public function get(StationId $id): Station;

    /** Nome já usado na filial? (case-insensitive, pela collation do MySQL) */
    public function nameExists(BranchId $branchId, string $name, ?StationId $except = null): bool;
}
