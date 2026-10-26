<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Domain\Identity\BranchId;

interface LocationRepository
{
    public function save(Location $location): void;

    /** @throws LocationNotFound */
    public function get(LocationId $id): Location;

    /** Rótulo já usado na filial? (case-insensitive, pela collation do MySQL) */
    public function labelExists(BranchId $branchId, string $label, ?LocationId $except = null): bool;
}
