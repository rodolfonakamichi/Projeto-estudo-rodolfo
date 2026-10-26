<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\Timestamped;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Raiz do agregado Role: papel da empresa com seu conjunto de permissões e o
 * limite de desconto (doc 06 §4; `null` = sem limite).
 *
 * Referencia a Company por objeto (e não só pelo CompanyId) para o Doctrine
 * gerar a FK — mas é referência somente-leitura: Role nunca altera a Company.
 */
class Role implements Timestamped
{
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, RolePermission> */
    private Collection $permissions;

    private function __construct(
        private RoleId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly Company $company,
        private readonly RoleCode $code,
        private string $name,
        private ?Percentage $maxDiscount,
    ) {
        $this->permissions = new ArrayCollection();
    }

    /**
     * @param list<Permission> $permissions
     */
    public static function create(
        RoleId $id,
        Company $company,
        RoleCode $code,
        string $name,
        array $permissions = [],
        ?Percentage $maxDiscount = null,
    ): self {
        $role = new self($id, $company, $code, self::assertName($name), $maxDiscount);
        foreach ($permissions as $permission) {
            $role->grant($permission);
        }

        return $role;
    }

    public function grant(Permission $permission): void
    {
        if (!$this->grants($permission)) {
            $this->permissions->add(new RolePermission($this, $permission));
        }
    }

    public function revoke(Permission $permission): void
    {
        foreach ($this->permissions as $row) {
            if ($row->permission() === $permission) {
                $this->permissions->removeElement($row);
            }
        }
    }

    public function grants(Permission $permission): bool
    {
        foreach ($this->permissions as $row) {
            if ($row->permission() === $permission) {
                return true;
            }
        }

        return false;
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        return array_values($this->permissions->map(static fn (RolePermission $rolePermission): Permission => $rolePermission->permission())->toArray());
    }

    public function limitDiscountTo(?Percentage $maxDiscount): void
    {
        $this->maxDiscount = $maxDiscount;
    }

    /** `null` = sem limite. */
    public function maxDiscount(): ?Percentage
    {
        return $this->maxDiscount;
    }

    public function rename(string $name): void
    {
        $this->name = self::assertName($name);
    }

    public function id(): RoleId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->company->id();
    }

    public function code(): RoleCode
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    private static function assertName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > 80) {
            throw new DomainException('Nome do papel deve ter entre 1 e 80 caracteres.');
        }

        return $name;
    }
}
