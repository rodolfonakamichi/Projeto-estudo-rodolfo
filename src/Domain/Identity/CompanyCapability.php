<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\Timestamped;

/**
 * Linha de `company_capabilities`. Parte do agregado Company — de fora só se
 * pergunta `Company::hasCapability()`.
 */
class CompanyCapability implements Timestamped
{
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    /** @internal use Company::enableCapability() */
    public function __construct(
        private readonly CompanyCapabilityId $id,
        private readonly Company $company,
        private readonly Capability $capability,
        private bool $enabled = true,
    ) {
    }

    public function capability(): Capability
    {
        return $this->capability;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /** @internal */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }
}
