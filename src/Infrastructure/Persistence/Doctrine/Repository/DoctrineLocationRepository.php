<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Attendance\Location;
use App\Domain\Attendance\LocationId;
use App\Domain\Attendance\LocationNotFound;
use App\Domain\Attendance\LocationRepository;
use App\Domain\Identity\BranchId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLocationRepository implements LocationRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Location $location): void
    {
        $this->entityManager->persist($location);
    }

    public function get(LocationId $id): Location
    {
        return $this->entityManager->find(Location::class, $id) ?? throw LocationNotFound::withId($id);
    }

    public function labelExists(BranchId $branchId, string $label, ?LocationId $except = null): bool
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(location.id)')
            ->from(Location::class, 'location')
            ->where('location.branchId = :branch AND location.label = :label')
            ->setParameter('branch', $branchId, 'branch_id')
            ->setParameter('label', trim($label));

        if (null !== $except) {
            $query->andWhere('location.id <> :except')->setParameter('except', $except, 'location_id');
        }

        return (int) $query->getQuery()->getSingleScalarResult() > 0;
    }
}
