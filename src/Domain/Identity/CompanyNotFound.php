<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\DomainException;

final class CompanyNotFound extends DomainException
{
    public static function withId(CompanyId $id): self
    {
        return new self("Empresa {$id} não encontrada.");
    }

    public static function withSlug(Slug $slug): self
    {
        return new self("Empresa \"{$slug->toString()}\" não encontrada.");
    }
}
