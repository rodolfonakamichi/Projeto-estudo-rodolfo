<?php

declare(strict_types=1);

namespace App\Application\Catalog\Category;

use App\Application\Identity\Authorization\BranchAuthorizer;
use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Catalog\CategoryId;
use App\Domain\Catalog\CategoryRepository;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use Psr\Clock\ClockInterface;

final readonly class ArchiveCategoryHandler implements CommandHandler
{
    public function __construct(
        private BranchAuthorizer $authorizer,
        private CategoryRepository $categories,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ArchiveCategory $command): void
    {
        $category = $this->categories->get(CategoryId::fromString($command->categoryId));
        $this->authorizer->authorize(UserId::fromString($command->actorId), $category->branchId(), Permission::CatalogProductManage);

        $category->archive($this->clock->now());
    }
}
