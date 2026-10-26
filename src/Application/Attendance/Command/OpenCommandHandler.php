<?php

declare(strict_types=1);

namespace App\Application\Attendance\Command;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Attendance\BindTarget;
use App\Domain\Attendance\BindType;
use App\Domain\Attendance\BusinessDay;
use App\Domain\Attendance\Command;
use App\Domain\Attendance\CommandId;
use App\Domain\Attendance\CommandRepository;
use App\Domain\Attendance\LocationId;
use App\Domain\Attendance\LocationNotFound;
use App\Domain\Attendance\LocationRepository;
use App\Domain\Attendance\LocationType;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyRepository;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;
use Psr\Clock\ClockInterface;

/**
 * Abre a comanda: vínculo válido, mesa livre, número do dia. A unicidade "uma
 * comanda aberta por mesa" é checada aqui (mensagem amigável) e garantida pelo
 * UNIQUE do banco (corrida entre dois garçons → 409).
 */
final readonly class OpenCommandHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private CompanyRepository $companies,
        private LocationRepository $locations,
        private CommandRepository $commands,
        private DisplayNumberSequence $numbers,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(OpenCommand $command): void
    {
        $actorId = UserId::fromString($command->actorId);
        $branchId = BranchId::fromString($command->branchId);
        $companyId = $this->authorizer->authorize($actorId, $branchId, Permission::CommandOpen);

        $type = BindType::tryFrom($command->bindType)
            ?? throw new DomainException(\sprintf('Vínculo inválido: "%s". Válidos: %s.', $command->bindType, implode(', ', array_column(BindType::cases(), 'value'))));
        $bind = BindTarget::of($type, $command->bindRef);

        $tableId = $bind->locationId();
        if (null !== $tableId) {
            $this->assertTableIsFree($branchId, $tableId);
        }

        $now = $this->clock->now();
        $businessDate = BusinessDay::of($now, $this->companies->get($companyId)->branch($branchId)->timezone());

        $this->commands->save(Command::open(
            CommandId::fromString($command->commandId),
            $companyId,
            $branchId,
            $bind,
            $this->numbers->next($companyId, $branchId, $businessDate),
            $businessDate,
            $actorId,
            $now,
        ));
    }

    private function assertTableIsFree(BranchId $branchId, LocationId $tableId): void
    {
        $table = $this->locations->get($tableId);
        if (!$table->branchId()->equals($branchId) || LocationType::Table !== $table->type()) {
            throw LocationNotFound::withId($tableId);
        }
        if (!$table->isActive()) {
            throw new DomainException("A {$table->label()} está desativada.");
        }

        $occupying = $this->commands->openOnTable($branchId, $tableId);
        if (null !== $occupying) {
            throw new DomainException("A {$table->label()} já tem a comanda #{$occupying->displayNumber()} aberta.");
        }
    }
}
