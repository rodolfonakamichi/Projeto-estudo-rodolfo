<?php

declare(strict_types=1);

namespace App\Application\Identity\Auth;

use App\Application\Shared\AccessDenied;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Device;
use App\Domain\Identity\DeviceId;
use App\Domain\Identity\DeviceRepository;
use App\Domain\Identity\OpaqueToken;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Identity\UserRepository;
use Psr\Clock\ClockInterface;

final readonly class PairDeviceHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private DeviceRepository $devices,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PairDevice $command): void
    {
        $user = $this->users->get(UserId::fromString($command->actingUserId));
        $branchId = BranchId::fromString($command->branchId);

        // Autorização no caso de uso (doc 06 §6), não no controller
        if (!$user->can(Permission::AdminDeviceManage, $branchId)) {
            throw AccessDenied::missing(Permission::AdminDeviceManage->value);
        }

        $this->devices->save(Device::pair(
            DeviceId::fromString($command->deviceId),
            $user->company()->branch($branchId),
            $user,
            OpaqueToken::fromPlain($command->plainToken),
            $command->name,
            $this->clock->now(),
        ));
    }
}
