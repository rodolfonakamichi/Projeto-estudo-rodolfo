<?php

declare(strict_types=1);

namespace App\Presentation\Http\Attendance;

use App\Application\Attendance\AttendanceQuery;
use App\Application\Attendance\Command\AddItemToCommand;
use App\Application\Attendance\Command\CancelCommand;
use App\Application\Attendance\Command\CancelCommandItem;
use App\Application\Attendance\Command\ChangeItemQuantity;
use App\Application\Attendance\Command\OpenCommand;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Attendance\CommandId;
use App\Domain\Attendance\CommandItemId;
use App\Domain\Attendance\CommandNotFound;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Infrastructure\Security\AuthenticatedUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Comandas da filial. Toda escrita devolve a comanda como ficou (itens e
 * totais recalculados), lida do read model.
 */
#[Route('/api')]
final readonly class CommandController
{
    public function __construct(
        private CommandBus $bus,
        private AttendanceQuery $attendance,
    ) {
    }

    #[Route('/branches/{branchId}/commands', methods: ['GET'], requirements: ['branchId' => Requirement::ULID])]
    public function list(#[CurrentUser] AuthenticatedUser $user, string $branchId): JsonResponse
    {
        return new JsonResponse(['commands' => $this->attendance->activeCommands(CompanyId::fromString($user->companyId), BranchId::fromString($branchId))]);
    }

    #[Route('/branches/{branchId}/commands', methods: ['POST'], requirements: ['branchId' => Requirement::ULID])]
    public function open(#[CurrentUser] AuthenticatedUser $user, string $branchId, #[MapRequestPayload] OpenCommandRequest $body): JsonResponse
    {
        $commandId = CommandId::generate()->toString();
        $this->bus->dispatch(new OpenCommand($commandId, $user->userId, $branchId, $body->bindType, $body->bindRef));

        return $this->command($user, $commandId, Response::HTTP_CREATED);
    }

    #[Route('/commands/{commandId}', methods: ['GET'], requirements: ['commandId' => Requirement::ULID])]
    public function show(#[CurrentUser] AuthenticatedUser $user, string $commandId): JsonResponse
    {
        return $this->command($user, $commandId);
    }

    #[Route('/commands/{commandId}/items', methods: ['POST'], requirements: ['commandId' => Requirement::ULID])]
    public function addItem(#[CurrentUser] AuthenticatedUser $user, string $commandId, #[MapRequestPayload] AddItemRequest $body): JsonResponse
    {
        $this->bus->dispatch(new AddItemToCommand(
            commandId: $commandId,
            itemId: CommandItemId::generate()->toString(),
            actorId: $user->userId,
            productId: $body->productId,
            variantId: $body->variantId,
            quantity: $body->quantity,
            modifierIds: $body->modifierIds,
            notes: $body->notes,
        ));

        return $this->command($user, $commandId, Response::HTTP_CREATED);
    }

    #[Route('/commands/{commandId}/items/{itemId}', methods: ['PATCH'], requirements: ['commandId' => Requirement::ULID, 'itemId' => Requirement::ULID])]
    public function changeQuantity(#[CurrentUser] AuthenticatedUser $user, string $commandId, string $itemId, #[MapRequestPayload] ItemQuantityRequest $body): JsonResponse
    {
        $this->bus->dispatch(new ChangeItemQuantity($commandId, $itemId, $user->userId, $body->quantity));

        return $this->command($user, $commandId);
    }

    #[Route('/commands/{commandId}/items/{itemId}/cancel', methods: ['POST'], requirements: ['commandId' => Requirement::ULID, 'itemId' => Requirement::ULID])]
    public function cancelItem(#[CurrentUser] AuthenticatedUser $user, string $commandId, string $itemId, #[MapRequestPayload] ReasonRequest $body): JsonResponse
    {
        $this->bus->dispatch(new CancelCommandItem($commandId, $itemId, $user->userId, $body->reason));

        return $this->command($user, $commandId);
    }

    #[Route('/commands/{commandId}/cancel', methods: ['POST'], requirements: ['commandId' => Requirement::ULID])]
    public function cancel(#[CurrentUser] AuthenticatedUser $user, string $commandId, #[MapRequestPayload] ReasonRequest $body): JsonResponse
    {
        $this->bus->dispatch(new CancelCommand($commandId, $user->userId, $body->reason));

        return $this->command($user, $commandId);
    }

    private function command(AuthenticatedUser $user, string $commandId, int $status = Response::HTTP_OK): JsonResponse
    {
        $id = CommandId::fromString($commandId);
        $command = $this->attendance->command(CompanyId::fromString($user->companyId), $id) ?? throw CommandNotFound::withId($id);

        return new JsonResponse($command, $status);
    }
}
