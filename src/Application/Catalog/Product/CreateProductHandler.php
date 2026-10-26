<?php

declare(strict_types=1);

namespace App\Application\Catalog\Product;

use App\Application\Catalog\CatalogReferences;
use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Catalog\NewVariant;
use App\Domain\Catalog\Product;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductRepository;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;

final readonly class CreateProductHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private CatalogReferences $references,
        private ProductRepository $products,
    ) {
    }

    public function __invoke(CreateProduct $command): void
    {
        $branchId = BranchId::fromString($command->branchId);
        // criar produto define preço: pede as duas permissões (doc 06)
        $companyId = $this->authorizer->authorize(UserId::fromString($command->actorId), $branchId, Permission::CatalogProductManage, Permission::CatalogPriceManage);

        $product = Product::create(
            ProductId::fromString($command->productId),
            $companyId,
            $branchId,
            $command->name,
            array_map(static fn (VariantInput $variant): NewVariant => $variant->toNewVariant(), $command->variants),
            $this->references->category($command->categoryId, $branchId),
            $this->references->station($command->stationId, $branchId),
        );
        $product->changeStockTracking($command->trackStock);

        foreach ($command->modifierGroups as $group) {
            $group->addTo($product);
        }

        $this->products->save($product);
    }
}
