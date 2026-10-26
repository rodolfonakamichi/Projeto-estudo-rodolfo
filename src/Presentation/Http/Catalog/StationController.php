<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use App\Application\Catalog\CatalogQuery;
use App\Application\Catalog\Station\CreateStation;
use App\Application\Catalog\Station\UpdateStation;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Catalog\StationId;
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
 * Estações de produção da filial. Escrita → caso de uso (que autoriza);
 * leitura → read model, com a empresa do token.
 * `Requirement::ULID` na rota: ID malformado nem chega aqui (404).
 */
#[Route('/api')]
final readonly class StationController
{
    public function __construct(private CommandBus $bus)
    {
    }

    #[Route('/branches/{branchId}/stations', methods: ['GET'], requirements: ['branchId' => Requirement::ULID])]
    public function list(#[CurrentUser] AuthenticatedUser $user, string $branchId, CatalogQuery $catalog): JsonResponse
    {
        return new JsonResponse(['stations' => $catalog->stations(CompanyId::fromString($user->companyId), BranchId::fromString($branchId))]);
    }

    #[Route('/branches/{branchId}/stations', methods: ['POST'], requirements: ['branchId' => Requirement::ULID])]
    public function create(#[CurrentUser] AuthenticatedUser $user, string $branchId, #[MapRequestPayload] StationRequest $body): JsonResponse
    {
        $stationId = StationId::generate();
        $this->bus->dispatch(new CreateStation($stationId->toString(), $user->userId, $branchId, $body->name));

        return new JsonResponse(['id' => $stationId->toString()], Response::HTTP_CREATED);
    }

    #[Route('/stations/{stationId}', methods: ['PATCH'], requirements: ['stationId' => Requirement::ULID])]
    public function update(#[CurrentUser] AuthenticatedUser $user, string $stationId, #[MapRequestPayload] UpdateStationRequest $body): Response
    {
        $this->bus->dispatch(new UpdateStation($stationId, $user->userId, $body->name, $body->active));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
