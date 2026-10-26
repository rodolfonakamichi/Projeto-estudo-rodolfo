<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Identity\Company;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\CompanyNotFound;
use App\Domain\Identity\CompanyRepository;
use App\Domain\Identity\Slug;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Adapter Doctrine da porta CompanyRepository.
 *
 * `save()` só faz persist: quem dá o flush (e o COMMIT) é o middleware
 * doctrine_transaction do command.bus, uma vez por caso de uso (Unit of Work).
 */
final readonly class DoctrineCompanyRepository implements CompanyRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Company $company): void
    {
        $this->entityManager->persist($company);
    }

    public function get(CompanyId $id): Company
    {
        return $this->entityManager->find(Company::class, $id) ?? throw CompanyNotFound::withId($id);
    }

    public function getBySlug(Slug $slug): Company
    {
        return $this->entityManager->getRepository(Company::class)->findOneBy(['slug' => $slug]) ?? throw CompanyNotFound::withSlug($slug);
    }

    public function slugExists(Slug $slug): bool
    {
        return null !== $this->entityManager->getRepository(Company::class)->findOneBy(['slug' => $slug]);
    }
}
