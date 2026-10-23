<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Atribuição de um papel a um usuário: na empresa toda (`branch = null`) ou
 * numa filial. Parte do agregado User.
 *
 * @internal use User::assignRole()
 */
class UserRole
{
    public function __construct(
        private UserRoleId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly User $user,
        private readonly Role $role,
        private readonly ?Branch $branch,
    ) {
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function branchId(): ?BranchId
    {
        return $this->branch?->id();
    }

    public function isCompanyWide(): bool
    {
        return null === $this->branch;
    }

    /** O papel vale nesta filial? (papel da empresa toda vale em qualquer uma) */
    public function appliesTo(?BranchId $branchId): bool
    {
        return null === $this->branch || (null !== $branchId && $this->branch->id()->equals($branchId));
    }

    public function matches(Role $role, ?Branch $branch): bool
    {
        return $this->role->id()->equals($role->id())
            && $this->branchId()?->toString() === $branch?->id()->toString();
    }
}
