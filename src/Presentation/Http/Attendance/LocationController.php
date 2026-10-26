<?php

declare(strict_types=1);

namespace App\Presentation\Http\Attendance;

use App\Application\Attendance\AttendanceQuery;
use App\Application\Attendance\Location\CreateLocation;
use App\Application\Attendance\Location\UpdateLocation;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Attendance\LocationId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Infrastructure\Security\AuthenticatedUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Locais da filial (mesas, balcão, áreas). Qualquer usuário da empresa lê; cadastrar pede `admin.location.manage`. */
#[Route('/api')]
final readonly class LocationController
{
    public function __construct(private CommandBus $bus)
    {
    }

    #[Route('/branches/{branchId}/locations', methods: ['GET'], requirements: ['branchId' => Requirement::ULID])]
    public function list(#[CurrentUser] AuthenticatedUser $user, string $branchId, AttendanceQuery $attendance): JsonResponse
    {
        return new JsonResponse(['locations' => $attendance->locations(CompanyId::fromString($user->companyId), BranchId::fromString($branchId))]);
    }

    #[Route('/branches/{branchId}/locations', methods: ['POST'], requirements: ['branchId' => Requirement::ULID])]
    public function create(#[CurrentUser] AuthenticatedUser $user, string $branchId, #[MapRequestPayload] LocationRequest $body): JsonResponse
    {
        $locationId = LocationId::generate();
        $this->bus->dispatch(new CreateLocation($locationId->toString(), $user->userId, $branchId, $body->type, $body->label));

        return new JsonResponse(['id' => $locationId->toString()], Response::HTTP_CREATED);
    }

    #[Route('/locations/{locationId}', methods: ['PATCH'], requirements: ['locationId' => Requirement::ULID])]
    public function update(#[CurrentUser] AuthenticatedUser $user, string $locationId, #[MapRequestPayload] UpdateLocationRequest $body): Response
    {
        $this->bus->dispatch(new UpdateLocation($locationId, $user->userId, $body->label, $body->active));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
