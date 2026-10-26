<?php

declare(strict_types=1);

namespace App\Application\Identity\Authorization;

use App\Application\Shared\AccessDenied;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Identity\UserNotFound;
use App\Domain\Identity\UserRepository;

/**
 * Checagem central de permissão (doc 06 §6). Os casos de uso chamam
 * `assert()`; o PermissionVoter expõe o mesmo `can()` ao Symfony Security.
 * A regra em si mora no Domain (`User::can`) — aqui só carrega o usuário.
 *
 * O ator vem explícito (não do CompanyContext): o mesmo caso de uso roda por
 * HTTP, CLI ou teste, e quem chama sempre diz em nome de quem age.
 */
final readonly class PermissionChecker
{
    public function __construct(private UserRepository $users)
    {
    }

    public function can(UserId $actor, Permission $permission, ?BranchId $branchId = null): bool
    {
        try {
            return $this->users->get($actor)->can($permission, $branchId);
        } catch (UserNotFound) {
            return false; // inclusive usuário de outra empresa, escondido pelo CompanyFilter
        }
    }

    /** @throws AccessDenied */
    public function assert(UserId $actor, Permission $permission, ?BranchId $branchId = null): void
    {
        if (!$this->can($actor, $permission, $branchId)) {
            throw AccessDenied::missing($permission->value);
        }
    }
}
