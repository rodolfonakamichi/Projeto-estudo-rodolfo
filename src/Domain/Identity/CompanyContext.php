<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Quem fez a requisição. Resolvido na autenticação a partir do bearer token;
 * dele sai o filtro `company_id = ?` de toda consulta do Doctrine (ADR-004).
 *
 * Dois tipos de ator: o usuário (token `u_`) e o aparelho pareado (token `d_`,
 * sem usuário, preso a uma filial).
 */
final readonly class CompanyContext
{
    public function __construct(
        public CompanyId $companyId,
        // null = aparelho pareado (ainda ninguém digitou o PIN)
        public ?UserId $userId,
        // null = ação no nível da empresa (não amarrada a uma filial)
        public ?BranchId $branchId = null,
    ) {
    }

    public static function forUser(CompanyId $companyId, UserId $userId): self
    {
        return new self($companyId, $userId);
    }

    public static function forDevice(CompanyId $companyId, BranchId $branchId): self
    {
        return new self($companyId, null, $branchId);
    }
}
