<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Application\Catalog\CatalogReferences;
use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductRepository;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;

final readonly class UpdateProductHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private CatalogReferences $references,
        private ProductRepository $products,
    ) {
    }

    public function __invoke(UpdateProduct $command): void
    {
        $product = $this->products->get(ProductId::fromString($command->productId));
        $this->authorizer->authorize(UserId::fromString($command->actorId), $product->branchId(), Permission::CatalogProductManage);

        if (null !== $command->name) {
            $product->rename($command->name);
        }
        if (null !== $command->trackStock) {
            $product->changeStockTracking($command->trackStock);
        }
        if ($command->changeCategory) {
            $product->moveToCategory($this->references->category($command->categoryId, $product->branchId()));
        }
        if ($command->changeStation) {
            $product->routeTo($this->references->station($command->stationId, $product->branchId()));
        }

        match ($command->active) {
            true => $product->activate(),
            false => $product->deactivate(),
            null => null,
        };
    }
}
