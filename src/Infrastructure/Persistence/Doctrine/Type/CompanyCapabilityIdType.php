<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Identity\CompanyCapabilityId;

final class CompanyCapabilityIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return CompanyCapabilityId::class;
    }
}
