<?php

declare(strict_types=1);

namespace App\Application\Catalog\Station;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Catalog\StationId;
use App\Domain\Catalog\StationRepository;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;

final readonly class UpdateStationHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private StationRepository $stations,
    ) {
    }

    public function __invoke(UpdateStation $command): void
    {
        $station = $this->stations->get(StationId::fromString($command->stationId));
        $this->authorizer->authorize(UserId::fromString($command->actorId), Permission::CatalogStationManage, $station->branchId());

        if (null !== $command->name) {
            if ($this->stations->nameExists($station->branchId(), $command->name, except: $station->id())) {
                throw new DomainException("Já existe a estação \"{$command->name}\" nesta filial.");
            }
            $station->rename($command->name);
        }

        match ($command->active) {
            true => $station->activate(),
            false => $station->deactivate(),
            null => null,
        };
    }
}
