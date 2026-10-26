<?php

declare(strict_types=1);

namespace App\Application\Attendance;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;

/** Leitura do atendimento (CQRS): SQL direto, `companyId` obrigatório (o filtro do ORM não chega aqui). */
interface AttendanceQuery
{
    /** @return list<LocationView> ativos e inativos, por tipo e nome */
    public function locations(CompanyId $companyId, BranchId $branchId): array;
}
