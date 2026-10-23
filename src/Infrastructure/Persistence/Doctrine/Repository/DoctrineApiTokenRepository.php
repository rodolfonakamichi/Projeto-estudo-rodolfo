<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Identity\ApiToken;
use App\Domain\Identity\ApiTokenRepository;
use App\Domain\Identity\OpaqueToken;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineApiTokenRepository implements ApiTokenRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(ApiToken $token): void
    {
        $this->em->persist($token);
    }

    public function findByToken(OpaqueToken $token): ?ApiToken
    {
        return $this->em->getRepository(ApiToken::class)->findOneBy(['tokenHash' => $token->hash()]);
    }
}
