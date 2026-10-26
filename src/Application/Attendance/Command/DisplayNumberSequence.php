<?php

declare(strict_types=1);

namespace App\Application\Attendance\Command;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;

/** Porta: próximo número amigável da comanda (Comanda 42) na filial e data operacional. */
interface DisplayNumberSequence
{
    public function next(CompanyId $companyId, BranchId $branchId, \DateTimeImmutable $businessDate): int;
}
