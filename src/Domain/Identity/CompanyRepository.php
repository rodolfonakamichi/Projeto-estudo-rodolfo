<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Porta de persistência do agregado Company (padrão Repository).
 * Implementação: Infrastructure\Persistence\Doctrine\Repository\DoctrineCompanyRepository.
 */
interface CompanyRepository
{
    public function save(Company $company): void;

    public function get(CompanyId $id): Company;

    public function getBySlug(Slug $slug): Company;

    public function slugExists(Slug $slug): bool;
}
