<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Catalog\Station;
use App\Domain\Catalog\StationId;
use App\Domain\Catalog\StationNotFound;
use App\Domain\Catalog\StationRepository;
use App\Domain\Identity\BranchId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineStationRepository implements StationRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Station $station): void
    {
        $this->entityManager->persist($station);
    }

    public function get(StationId $id): Station
    {
        return $this->entityManager->find(Station::class, $id) ?? throw StationNotFound::withId($id);
    }

    public function nameExists(BranchId $branchId, string $name, ?StationId $except = null): bool
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(station.id)')
            ->from(Station::class, 'station')
            ->where('station.branchId = :branch AND station.name = :name')
            ->setParameter('branch', $branchId, 'branch_id')
            ->setParameter('name', trim($name));

        if (null !== $except) {
            $query->andWhere('station.id <> :except')->setParameter('except', $except, 'station_id');
        }

        return (int) $query->getQuery()->getSingleScalarResult() > 0;
    }
}
