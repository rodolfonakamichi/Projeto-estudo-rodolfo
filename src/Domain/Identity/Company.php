<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFound;
use App\Domain\Shared\Timestamped;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Raiz do agregado Company: a empresa cliente, suas filiais e capabilities.
 *
 * Invariantes (doc 02):
 *  - nasce com ao menos uma filial, e nunca fica sem filial ativa;
 *  - nome de filial é único dentro da empresa.
 * (Slug único na plataforma é checado no caso de uso + índice UNIQUE.)
 *
 * As coleções são `Collection` do Doctrine (ADR-012) mas nunca saem daqui:
 * para fora, só listas imutáveis.
 */
class Company implements Timestamped
{
    private CompanyStatus $status = CompanyStatus::Active;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Branch> */
    private Collection $branches;

    /** @var Collection<int, CompanyCapability> */
    private Collection $capabilities;

    private function __construct(
        private CompanyId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private string $name,
        private readonly Slug $slug,
    ) {
        $this->branches = new ArrayCollection();
        $this->capabilities = new ArrayCollection();
    }

    /** Factory: a única forma de criar uma empresa — já com a primeira filial. */
    public static function register(
        CompanyId $id,
        string $name,
        Slug $slug,
        BranchId $firstBranchId,
        string $firstBranchName,
        string $timezone = 'America/Sao_Paulo',
    ): self {
        $company = new self($id, self::assertName($name), $slug);
        $company->addBranch($firstBranchId, $firstBranchName, $timezone);

        return $company;
    }

    public function addBranch(BranchId $id, string $name, string $timezone): Branch
    {
        $name = self::assertName($name);

        foreach ($this->branches as $branch) {
            if (0 === strcasecmp($branch->name(), $name)) {
                throw new DomainException("Já existe uma filial chamada \"{$name}\".");
            }
        }

        $branch = new Branch($id, $this, $name, $timezone);
        $this->branches->add($branch);

        return $branch;
    }

    public function deactivateBranch(BranchId $id): void
    {
        $branch = $this->branch($id);

        if ($branch->isActive() && 1 === \count($this->activeBranches())) {
            throw new DomainException('A empresa precisa de ao menos uma filial ativa.');
        }

        $branch->deactivate();
    }

    public function branch(BranchId $id): Branch
    {
        foreach ($this->branches as $branch) {
            if ($branch->id()->equals($id)) {
                return $branch;
            }
        }

        throw new NotFound('Filial não encontrada nesta empresa.');
    }

    /** @return list<Branch> */
    public function branches(): array
    {
        return array_values($this->branches->toArray());
    }

    /** @return list<Branch> */
    public function activeBranches(): array
    {
        return array_values(array_filter($this->branches(), static fn (Branch $branch): bool => $branch->isActive()));
    }

    public function enableCapability(Capability $capability): void
    {
        $existing = $this->findCapability($capability);

        if (null !== $existing) {
            $existing->setEnabled(true);

            return;
        }

        $this->capabilities->add(new CompanyCapability(CompanyCapabilityId::generate(), $this, $capability));
    }

    public function disableCapability(Capability $capability): void
    {
        $this->findCapability($capability)?->setEnabled(false);
    }

    public function hasCapability(Capability $capability): bool
    {
        return $this->findCapability($capability)?->isEnabled() ?? false;
    }

    /** @return list<Capability> */
    public function enabledCapabilities(): array
    {
        $enabled = [];
        foreach ($this->capabilities as $row) {
            if ($row->isEnabled()) {
                $enabled[] = $row->capability();
            }
        }

        return $enabled;
    }

    public function suspend(): void
    {
        $this->status = CompanyStatus::Suspended;
    }

    public function reactivate(): void
    {
        $this->status = CompanyStatus::Active;
    }

    public function rename(string $name): void
    {
        $this->name = self::assertName($name);
    }

    public function id(): CompanyId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): Slug
    {
        return $this->slug;
    }

    public function status(): CompanyStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return CompanyStatus::Active === $this->status;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    private function findCapability(Capability $capability): ?CompanyCapability
    {
        foreach ($this->capabilities as $row) {
            if ($row->capability() === $capability) {
                return $row;
            }
        }

        return null;
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
