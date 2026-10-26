<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Identity\CompanyId;
use App\Domain\Identity\Role;
use App\Domain\Identity\RoleCode;
use App\Domain\Identity\RoleNotFound;
use App\Domain\Identity\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRoleRepository implements RoleRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Role $role): void
    {
        $this->entityManager->persist($role);
    }

    public function getByCode(CompanyId $companyId, RoleCode $code): Role
    {
        return $this->entityManager->getRepository(Role::class)->findOneBy(['company' => $companyId, 'code' => $code])
            ?? throw RoleNotFound::withCode($code);
    }
}
