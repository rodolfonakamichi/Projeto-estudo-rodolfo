<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

interface ProductRepository
{
    /** Grava o agregado inteiro (variações, grupos e modificadores vão em cascata). */
    public function save(Product $product): void;

    /** @throws ProductNotFound */
    public function get(ProductId $id): Product;
}
