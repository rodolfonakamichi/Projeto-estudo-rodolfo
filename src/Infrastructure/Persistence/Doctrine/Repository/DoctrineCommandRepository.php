<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Attendance\Command;
use App\Domain\Attendance\CommandId;
use App\Domain\Attendance\CommandNotFound;
use App\Domain\Attendance\CommandRepository;
use App\Domain\Attendance\LocationId;
use App\Domain\Identity\BranchId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCommandRepository implements CommandRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Command $command): void
    {
        $this->entityManager->persist($command);
    }

    public function get(CommandId $id): Command
    {
        return $this->entityManager->find(Command::class, $id) ?? throw CommandNotFound::withId($id);
    }

    public function openOnTable(BranchId $branchId, LocationId $tableId): ?Command
    {
        return $this->entityManager->getRepository(Command::class)->findOneBy(['branchId' => $branchId, 'openTableRef' => $tableId->toString()]);
    }
}
