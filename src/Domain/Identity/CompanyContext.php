<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Quem fez a requisição. Resolvido pelo middleware de autenticação (M1.f) a
 * partir do bearer token e injetado em todo repositório (ADR-004) — todo SQL
 * de negócio filtra por `company_id`.
 */
final readonly class CompanyContext
{
    public function __construct(
        public CompanyId $companyId,
        public UserId $userId,
        // null = ação no nível da empresa (não amarrada a uma filial)
        public ?BranchId $branchId = null,
    ) {
    }
}
