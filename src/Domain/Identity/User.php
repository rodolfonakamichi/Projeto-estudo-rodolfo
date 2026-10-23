<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\Timestamped;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Raiz do agregado User: pessoa que opera o sistema dentro de UMA empresa.
 *
 * Invariantes:
 *  - papel e filial atribuídos são da mesma empresa do usuário;
 *  - o mesmo papel não é atribuído duas vezes no mesmo escopo;
 *  - usuário inativo não tem permissão nenhuma.
 * (E-mail único por empresa: caso de uso + índice UNIQUE.)
 *
 * Senha e PIN chegam já em hash — o Domain não sabe qual algoritmo é
 * (porta PasswordHasher, implementada na Infrastructure).
 */
class User implements Timestamped
{
    private UserStatus $status = UserStatus::Active;
    private ?string $pinHash = null;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, UserRole> */
    private Collection $roles;

    private function __construct(
        private UserId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly Company $company,
        private string $name,
        private Email $email,
        private string $passwordHash,
    ) {
        $this->roles = new ArrayCollection();
    }

    public static function register(UserId $id, Company $company, string $name, Email $email, string $passwordHash): self
    {
        return new self($id, $company, self::assertName($name), $email, $passwordHash);
    }

    /** @param Branch|null $branch null = papel válido na empresa toda */
    public function assignRole(Role $role, ?Branch $branch = null): void
    {
        if (!$role->companyId()->equals($this->companyId())) {
            throw new DomainException('O papel pertence a outra empresa.');
        }
        if (null !== $branch && !$branch->companyId()->equals($this->companyId())) {
            throw new DomainException('A filial pertence a outra empresa.');
        }

        foreach ($this->roles as $assignment) {
            if ($assignment->matches($role, $branch)) {
                throw new DomainException("O usuário já tem o papel {$role->code()->toString()} neste escopo.");
            }
        }

        $this->roles->add(new UserRole(UserRoleId::generate(), $this, $role, $branch));
    }

    public function revokeRole(Role $role, ?Branch $branch = null): void
    {
        foreach ($this->roles as $assignment) {
            if ($assignment->matches($role, $branch)) {
                $this->roles->removeElement($assignment);
            }
        }
    }

    /**
     * Pode executar a ação nesta filial? `null` = ação no nível da empresa
     * (só papéis da empresa toda contam). Coração da autorização (doc 06).
     */
    public function can(Permission $permission, ?BranchId $branchId = null): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        foreach ($this->rolesIn($branchId) as $role) {
            if ($role->grants($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Maior desconto que o usuário aplica sozinho nesta filial (doc 06 §4).
     * `null` = sem limite. Sem papel com `command.discount.apply` = 0%.
     */
    public function maxDiscountIn(?BranchId $branchId): ?Percentage
    {
        $best = Percentage::fromHundredths(0);

        foreach ($this->rolesIn($branchId) as $role) {
            if (!$role->grants(Permission::CommandDiscountApply)) {
                continue;
            }
            $limit = $role->maxDiscount();
            if (null === $limit) {
                return null;
            }
            if ($limit->isGreaterThan($best)) {
                $best = $limit;
            }
        }

        return $best;
    }

    /** @return list<UserRole> */
    public function roleAssignments(): array
    {
        return array_values($this->roles->toArray());
    }

    public function changePassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function changePin(?string $pinHash): void
    {
        $this->pinHash = $pinHash;
    }

    public function changeEmail(Email $email): void
    {
        $this->email = $email;
    }

    public function rename(string $name): void
    {
        $this->name = self::assertName($name);
    }

    public function deactivate(): void
    {
        $this->status = UserStatus::Inactive;
    }

    public function activate(): void
    {
        $this->status = UserStatus::Active;
    }

    public function isActive(): bool
    {
        return UserStatus::Active === $this->status;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->company->id();
    }

    /** Referência somente-leitura (para outros agregados apontarem a FK). */
    public function company(): Company
    {
        return $this->company;
    }

    /** Tem algum papel válido nesta filial? (pré-requisito do login por PIN no aparelho) */
    public function worksAt(BranchId $branchId): bool
    {
        foreach ($this->roles as $assignment) {
            if ($assignment->appliesTo($branchId)) {
                return $this->isActive();
            }
        }

        return false;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function pinHash(): ?string
    {
        return $this->pinHash;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    /** @return iterable<Role> */
    private function rolesIn(?BranchId $branchId): iterable
    {
        foreach ($this->roles as $assignment) {
            if ($assignment->appliesTo($branchId)) {
                yield $assignment->role();
            }
        }
    }

    private static function assertName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > 150) {
            throw new DomainException('Nome deve ter entre 1 e 150 caracteres.');
        }

        return $name;
    }
}
