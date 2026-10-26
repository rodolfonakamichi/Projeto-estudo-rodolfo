<?php

declare(strict_types=1);

namespace App\Application\Attendance\Location;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Attendance\LocationId;
use App\Domain\Attendance\LocationRepository;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;

final readonly class UpdateLocationHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private LocationRepository $locations,
    ) {
    }

    public function __invoke(UpdateLocation $command): void
    {
        $location = $this->locations->get(LocationId::fromString($command->locationId));
        $this->authorizer->authorize(UserId::fromString($command->actorId), $location->branchId(), Permission::AdminLocationManage);

        if (null !== $command->label) {
            if ($this->locations->labelExists($location->branchId(), $command->label, except: $location->id())) {
                throw new DomainException("Já existe o local \"{$command->label}\" nesta filial.");
            }
            $location->relabel($command->label);
        }

        match ($command->active) {
            true => $location->activate(),
            false => $location->deactivate(),
            null => null,
        };
    }
}
