<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Catalog\OperatorFavorites;
use App\Domain\Catalog\OperatorFavoritesRepository;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineOperatorFavoritesRepository implements OperatorFavoritesRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(OperatorFavorites $favorites): void
    {
        $this->entityManager->persist($favorites);
    }

    public function findFor(UserId $userId, BranchId $branchId): ?OperatorFavorites
    {
        return $this->entityManager->getRepository(OperatorFavorites::class)->findOneBy(['userId' => $userId, 'branchId' => $branchId]);
    }
}
