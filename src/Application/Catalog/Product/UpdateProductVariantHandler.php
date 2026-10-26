<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductRepository;
use App\Domain\Catalog\ProductVariantId;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Shared\Money;

final readonly class UpdateProductVariantHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private ProductRepository $products,
    ) {
    }

    public function __invoke(UpdateProductVariant $command): void
    {
        $product = $this->products->get(ProductId::fromString($command->productId));
        $required = null === $command->priceCents
            ? [Permission::CatalogProductManage]
            : [Permission::CatalogProductManage, Permission::CatalogPriceManage];
        $this->authorizer->authorize(UserId::fromString($command->actorId), $product->branchId(), ...$required);

        $variantId = ProductVariantId::fromString($command->variantId);

        if (null !== $command->name) {
            $product->renameVariant($variantId, $command->name);
        }
        if (null !== $command->priceCents) {
            $product->changeVariantPrice($variantId, Money::fromCents($command->priceCents));
        }

        match ($command->active) {
            true => $product->activateVariant($variantId),
            false => $product->deactivateVariant($variantId),
            null => null,
        };
    }
}
