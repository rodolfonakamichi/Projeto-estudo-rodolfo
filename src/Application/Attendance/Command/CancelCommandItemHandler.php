<?php

declare(strict_types=1);

namespace App\Application\Attendance\Command;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Attendance\CommandId;
use App\Domain\Attendance\CommandItemId;
use App\Domain\Attendance\CommandRepository;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use Psr\Clock\ClockInterface;

final readonly class CancelCommandItemHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private CommandRepository $commands,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CancelCommandItem $command): void
    {
        $actorId = UserId::fromString($command->actorId);
        $tab = $this->commands->get(CommandId::fromString($command->commandId));
        $this->authorizer->authorize($actorId, $tab->branchId(), Permission::CommandItemCancel);

        $tab->cancelItem(CommandItemId::fromString($command->itemId), $actorId, $command->reason, $this->clock->now());
    }
}
