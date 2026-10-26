<?php

declare(strict_types=1);

namespace App\Application\Attendance\Command;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Attendance\CommandId;
use App\Domain\Attendance\CommandItemId;
use App\Domain\Attendance\CommandRepository;
use App\Domain\Attendance\Quantity;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use Psr\Clock\ClockInterface;

/** Mesma permissão de lançar (`command.item.add`); reduzir a zero não existe — isso é cancelar o item. */
final readonly class ChangeItemQuantityHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private CommandRepository $commands,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ChangeItemQuantity $command): void
    {
        $actorId = UserId::fromString($command->actorId);
        $tab = $this->commands->get(CommandId::fromString($command->commandId));
        $this->authorizer->authorize($actorId, $tab->branchId(), Permission::CommandItemAdd);

        $tab->changeItemQuantity(CommandItemId::fromString($command->itemId), Quantity::of($command->quantity), $actorId, $this->clock->now());
    }
}
