<?php

declare(strict_types=1);

namespace App\Application\Catalog\Category;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Catalog\Category;
use App\Domain\Catalog\CategoryId;
use App\Domain\Catalog\CategoryRepository;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;

final readonly class CreateCategoryHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private CategoryRepository $categories,
    ) {
    }

    public function __invoke(CreateCategory $command): void
    {
        $branchId = BranchId::fromString($command->branchId);
        $companyId = $this->authorizer->authorize(UserId::fromString($command->actorId), $branchId, Permission::CatalogProductManage);

        if ($this->categories->nameExists($branchId, $command->name)) {
            throw new DomainException("Já existe a categoria \"{$command->name}\" nesta filial.");
        }

        $this->categories->save(Category::create(
            CategoryId::fromString($command->categoryId),
            $companyId,
            $branchId,
            $command->name,
            $command->sortOrder,
        ));
    }
}
