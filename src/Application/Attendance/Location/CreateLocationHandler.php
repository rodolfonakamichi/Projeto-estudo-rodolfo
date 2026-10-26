<?php

declare(strict_types=1);

namespace App\Application\Attendance\Location;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Identity\Authorization\CapabilityChecker;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Attendance\Location;
use App\Domain\Attendance\LocationId;
use App\Domain\Attendance\LocationRepository;
use App\Domain\Attendance\LocationType;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;

final readonly class CreateLocationHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private CapabilityChecker $capabilities,
        private LocationRepository $locations,
    ) {
    }

    public function __invoke(CreateLocation $command): void
    {
        $branchId = BranchId::fromString($command->branchId);
        $companyId = $this->authorizer->authorize(UserId::fromString($command->actorId), $branchId, Permission::AdminLocationManage);

        $type = LocationType::tryFrom($command->type)
            ?? throw new DomainException(\sprintf('Tipo de local inválido: "%s". Válidos: %s.', $command->type, implode(', ', array_column(LocationType::cases(), 'value'))));

        // mesa só com `tables`, quarto só com `hotel` (ADR-001: o core pergunta pela capability)
        $capability = $type->requiredCapability();
        if (null !== $capability && !$this->capabilities->has($companyId, $capability)) {
            throw new DomainException("Local do tipo {$type->value} exige a capability \"{$capability->value}\" ligada na empresa.");
        }
        if ($this->locations->labelExists($branchId, $command->label)) {
            throw new DomainException("Já existe o local \"{$command->label}\" nesta filial.");
        }

        $this->locations->save(Location::create(LocationId::fromString($command->locationId), $companyId, $branchId, $type, $command->label));
    }
}
