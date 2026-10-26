<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use App\Application\Catalog\CatalogQuery;
use App\Application\Catalog\Favorites\SetFavoriteProducts;
use App\Application\Catalog\ShortcutsView;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Catalog\OperatorFavoritesId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use App\Infrastructure\Security\AuthenticatedUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Atalhos de lançamento do operador logado, por filial (favoritos agora; recentes e mais vendidos no M3). */
#[Route('/api/branches/{branchId}/shortcuts', requirements: ['branchId' => Requirement::ULID])]
final readonly class ShortcutsController
{
    public function __construct(
        private CommandBus $bus,
        private CatalogQuery $catalog,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function show(#[CurrentUser] AuthenticatedUser $user, string $branchId): JsonResponse
    {
        return $this->shortcuts($user, $branchId);
    }

    /** Troca a lista inteira (o PWA manda a ordem final depois de arrastar). */
    #[Route('/favorites', methods: ['PUT'])]
    public function setFavorites(#[CurrentUser] AuthenticatedUser $user, string $branchId, #[MapRequestPayload] FavoritesRequest $body): JsonResponse
    {
        $this->bus->dispatch(new SetFavoriteProducts(OperatorFavoritesId::generate()->toString(), $user->userId, $branchId, $body->productIds));

        return $this->shortcuts($user, $branchId);
    }

    private function shortcuts(AuthenticatedUser $user, string $branchId): JsonResponse
    {
        return new JsonResponse(new ShortcutsView(
            favorites: $this->catalog->favorites(CompanyId::fromString($user->companyId), BranchId::fromString($branchId), UserId::fromString($user->userId)),
        ));
    }
}
