<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Catalog\Product;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductNotFound;
use App\Domain\Catalog\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineProductRepository implements ProductRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Product $product): void
    {
        $this->entityManager->persist($product);
    }

    public function get(ProductId $id): Product
    {
        return $this->entityManager->find(Product::class, $id) ?? throw ProductNotFound::withId($id);
    }
}
