<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Catalog\Category;
use App\Domain\Catalog\CategoryId;
use App\Domain\Catalog\CategoryNotFound;
use App\Domain\Catalog\CategoryRepository;
use App\Domain\Identity\BranchId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCategoryRepository implements CategoryRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Category $category): void
    {
        $this->entityManager->persist($category);
    }

    public function get(CategoryId $id): Category
    {
        return $this->entityManager->find(Category::class, $id) ?? throw CategoryNotFound::withId($id);
    }

    public function nameExists(BranchId $branchId, string $name, ?CategoryId $except = null): bool
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(category.id)')
            ->from(Category::class, 'category')
            ->where('category.branchId = :branch AND category.name = :name')
            ->setParameter('branch', $branchId, 'branch_id')
            ->setParameter('name', trim($name));

        if (null !== $except) {
            $query->andWhere('category.id <> :except')->setParameter('except', $except, 'category_id');
        }

        return (int) $query->getQuery()->getSingleScalarResult() > 0;
    }
}
