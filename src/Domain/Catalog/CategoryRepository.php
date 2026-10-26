<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Identity\BranchId;

interface CategoryRepository
{
    public function save(Category $category): void;

    /** @throws CategoryNotFound */
    public function get(CategoryId $id): Category;

    /** Nome já usado na filial? (case-insensitive, pela collation do MySQL) */
    public function nameExists(BranchId $branchId, string $name, ?CategoryId $except = null): bool;
}
