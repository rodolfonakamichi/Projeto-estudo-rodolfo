<?php

declare(strict_types=1);

namespace App\Application\Identity\Authorization;

use App\Application\Shared\AccessDenied;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Identity\UserRepository;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFound;

/**
 * "O ator pode fazer X NESTA filial?" — o portão de todo caso de uso que mexe
 * em dado de filial (catálogo, mesas, caixa...). Garante, nesta ordem:
 *   1. a filial é da empresa do ator (senão 404 — não revela que existe);
 *   2. a filial está ativa;
 *   3. o ator tem TODAS as permissões pedidas nela (senão 403).
 * Devolve a empresa, que o caso de uso grava no agregado.
 *
 * Sem o passo 1, um papel "da empresa toda" passaria no `can()` com o ID de
 * uma filial de OUTRA empresa (o `can()` só olha papéis, não a filial).
 */
final readonly class BranchAuthorizer
{
    public function __construct(
        private PermissionChecker $permissions,
        private UserRepository $users,
    ) {
    }

    /**
     * @throws NotFound        filial inexistente ou de outra empresa
     * @throws DomainException filial inativa
     * @throws AccessDenied    sem a permissão
     */
    public function authorize(UserId $actorId, BranchId $branchId, Permission $permission, Permission ...$morePermissions): CompanyId
    {
        $company = $this->users->get($actorId)->company();

        if (!$company->branch($branchId)->isActive()) {
            throw new DomainException('Filial inativa.');
        }

        foreach ([$permission, ...$morePermissions] as $required) {
            $this->permissions->assert($actorId, $required, $branchId);
        }

        return $company->id();
    }
}
