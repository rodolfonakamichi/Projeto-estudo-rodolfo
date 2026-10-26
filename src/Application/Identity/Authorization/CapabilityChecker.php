<?php

declare(strict_types=1);

namespace App\Application\Identity\Authorization;

use App\Domain\Identity\Capability;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\CompanyRepository;

/**
 * "Esta empresa tem a capability X?" (ADR-001). O core pergunta isso — nunca
 * "que tipo de negócio é este?". Módulos (cozinha, estoque, mesas) e rotas
 * dependentes de capability consultam aqui.
 */
final readonly class CapabilityChecker
{
    public function __construct(private CompanyRepository $companies)
    {
    }

    public function has(CompanyId $companyId, Capability $capability): bool
    {
        return $this->companies->get($companyId)->hasCapability($capability);
    }
}
