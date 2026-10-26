<?php

declare(strict_types=1);

namespace App\Application\Catalog\Category;

use App\Application\Shared\Bus\Command;

final readonly class CreateCategory implements Command
{
    public function __construct(
        public string $categoryId,
        public string $actorId,
        public string $branchId,
        public string $name,
        public int $sortOrder = 0,
    ) {
    }
}
