<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Identity\CompanyId;
use App\Domain\Identity\Email;
use App\Domain\Identity\User;
use App\Domain\Identity\UserId;
use App\Domain\Identity\UserNotFound;
use App\Domain\Identity\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(User $user): void
    {
        $this->em->persist($user);
    }

    public function get(UserId $id): User
    {
        return $this->em->find(User::class, $id) ?? throw UserNotFound::withId($id);
    }

    public function emailExists(CompanyId $companyId, Email $email): bool
    {
        return null !== $this->em->getRepository(User::class)->findOneBy(['company' => $companyId, 'email' => $email]);
    }
}
