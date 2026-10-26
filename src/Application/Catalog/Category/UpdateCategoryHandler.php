<?php

declare(strict_types=1);

namespace App\Application\Catalog\Category;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Catalog\CategoryId;
use App\Domain\Catalog\CategoryRepository;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;

final readonly class UpdateCategoryHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private CategoryRepository $categories,
    ) {
    }

    public function __invoke(UpdateCategory $command): void
    {
        $category = $this->categories->get(CategoryId::fromString($command->categoryId));
        $this->authorizer->authorize(UserId::fromString($command->actorId), $category->branchId(), Permission::CatalogProductManage);

        if (null !== $command->name) {
            if ($this->categories->nameExists($category->branchId(), $command->name, except: $category->id())) {
                throw new DomainException("Já existe a categoria \"{$command->name}\" nesta filial.");
            }
            $category->rename($command->name);
        }

        if (null !== $command->sortOrder) {
            $category->moveTo($command->sortOrder);
        }
    }
}
