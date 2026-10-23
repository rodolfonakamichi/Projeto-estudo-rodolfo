<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\DomainException;
use App\Domain\Shared\Timestamped;

/**
 * Filial. Faz parte do agregado Company: só é criada/alterada através da
 * Company, que garante as regras que envolvem o conjunto (nome único, ao menos
 * uma filial ativa).
 */
class Branch implements Timestamped
{
    private BranchStatus $status = BranchStatus::Active;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    /** @internal use Company::addBranch() */
    public function __construct(
        private readonly BranchId $id,
        private readonly Company $company,
        private string $name,
        private string $timezone,
    ) {
        self::assertValidTimezone($timezone);
    }

    public function id(): BranchId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->company->id();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function status(): BranchStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return BranchStatus::Active === $this->status;
    }

    /** @internal use Company::deactivateBranch() */
    public function deactivate(): void
    {
        $this->status = BranchStatus::Inactive;
    }

    public function activate(): void
    {
        $this->status = BranchStatus::Active;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    private static function assertValidTimezone(string $timezone): void
    {
        if (!\in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new DomainException("Fuso horário inválido: \"{$timezone}\"");
        }
    }
}
