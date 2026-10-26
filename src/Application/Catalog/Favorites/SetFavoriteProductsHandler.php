<?php

declare(strict_types=1);

namespace App\Application\Catalog\Favorites;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Catalog\OperatorFavorites;
use App\Domain\Catalog\OperatorFavoritesId;
use App\Domain\Catalog\OperatorFavoritesRepository;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductNotFound;
use App\Domain\Catalog\ProductRepository;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;

/**
 * Favorito serve para LANÇAR item: pede `command.item.add` na filial (garçom,
 * caixa, gerente...). Cozinha/estoque não têm por que ter atalho de venda.
 * O `favoritesId` só é usado se o operador ainda não tinha lista.
 */
final readonly class SetFavoriteProductsHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private ProductRepository $products,
        private OperatorFavoritesRepository $favorites,
    ) {
    }

    public function __invoke(SetFavoriteProducts $command): void
    {
        $actorId = UserId::fromString($command->actorId);
        $branchId = BranchId::fromString($command->branchId);
        $companyId = $this->authorizer->authorize($actorId, $branchId, Permission::CommandItemAdd);

        $productIds = array_map(fn (string $id): ProductId => $this->sellableIn($branchId, ProductId::fromString($id)), $command->productIds);

        $favorites = $this->favorites->findFor($actorId, $branchId);
        if (null === $favorites) {
            $favorites = OperatorFavorites::start(OperatorFavoritesId::fromString($command->favoritesId), $companyId, $branchId, $actorId);
            $this->favorites->save($favorites);
        }

        $favorites->replace($productIds);
    }

    /** Produto da mesma filial (senão 404) e ativo. */
    private function sellableIn(BranchId $branchId, ProductId $productId): ProductId
    {
        $product = $this->products->get($productId);
        if (!$product->branchId()->equals($branchId)) {
            throw ProductNotFound::withId($productId);
        }
        if (!$product->isActive()) {
            throw new DomainException("O produto \"{$product->name()}\" está inativo.");
        }

        return $productId;
    }
}
