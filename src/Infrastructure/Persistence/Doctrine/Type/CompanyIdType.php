<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Identity\CompanyId;

final class CompanyIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return CompanyId::class;
    }
}
