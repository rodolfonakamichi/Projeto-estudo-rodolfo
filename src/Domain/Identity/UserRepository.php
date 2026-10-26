<?php

declare(strict_types=1);

namespace App\Domain\Identity;

interface UserRepository
{
    public function save(User $user): void;

    public function get(UserId $id): User;

    public function emailExists(CompanyId $companyId, Email $email): bool;

    public function findByEmail(CompanyId $companyId, Email $email): ?User;
}
