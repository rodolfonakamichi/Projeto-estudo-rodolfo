<?php

declare(strict_types=1);

namespace App\Application\Catalog\Station;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Identity\Authorization\CapabilityChecker;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Catalog\Station;
use App\Domain\Catalog\StationId;
use App\Domain\Catalog\StationRepository;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Capability;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;

final readonly class CreateStationHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private CapabilityChecker $capabilities,
        private StationRepository $stations,
    ) {
    }

    public function __invoke(CreateStation $command): void
    {
        $branchId = BranchId::fromString($command->branchId);
        $companyId = $this->authorizer->authorize(UserId::fromString($command->actorId), $branchId, Permission::CatalogStationManage);

        if (!$this->capabilities->has($companyId, Capability::Kitchen)) {
            throw new DomainException('Estações de produção exigem a capability "kitchen" ligada na empresa.');
        }
        if ($this->stations->nameExists($branchId, $command->name)) {
            throw new DomainException("Já existe a estação \"{$command->name}\" nesta filial.");
        }

        $this->stations->save(Station::create(StationId::fromString($command->stationId), $companyId, $branchId, $command->name));
    }
}
