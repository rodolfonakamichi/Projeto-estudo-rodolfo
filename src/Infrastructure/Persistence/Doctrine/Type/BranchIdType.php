<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Identity\BranchId;

final class BranchIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return BranchId::class;
    }
}
