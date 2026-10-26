<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Catalog\ModifierGroupId;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductRepository;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;

final readonly class ChangeModifierGroupLimitsHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private ProductRepository $products,
    ) {
    }

    public function __invoke(ChangeModifierGroupLimits $command): void
    {
        $product = $this->products->get(ProductId::fromString($command->productId));
        $this->authorizer->authorize(UserId::fromString($command->actorId), $product->branchId(), Permission::CatalogProductManage);

        $product->changeModifierGroupLimits(ModifierGroupId::fromString($command->groupId), $command->minSelect, $command->maxSelect);
    }
}
