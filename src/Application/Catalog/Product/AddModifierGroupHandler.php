<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductRepository;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;

final readonly class AddModifierGroupHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private ProductRepository $products,
    ) {
    }

    public function __invoke(AddModifierGroup $command): void
    {
        $product = $this->products->get(ProductId::fromString($command->productId));
        $this->authorizer->authorize(UserId::fromString($command->actorId), $product->branchId(), Permission::CatalogProductManage, Permission::CatalogPriceManage);

        $command->group->addTo($product);
    }
}
