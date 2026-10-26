<?php

declare(strict_types=1);

namespace App\Application\Attendance;

use App\Domain\Attendance\CommandId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;

/** Leitura do atendimento (CQRS): SQL direto, `companyId` obrigatório (o filtro do ORM não chega aqui). */
interface AttendanceQuery
{
    /** @return list<LocationView> ativos e inativos, por tipo e nome */
    public function locations(CompanyId $companyId, BranchId $branchId): array;

    /** @return list<CommandSummaryView> comandas em andamento (não fechadas nem canceladas), por número */
    public function activeCommands(CompanyId $companyId, BranchId $branchId): array;

    public function command(CompanyId $companyId, CommandId $commandId): ?CommandView;
}
