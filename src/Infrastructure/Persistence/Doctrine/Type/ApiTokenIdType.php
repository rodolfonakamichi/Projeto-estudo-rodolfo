<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Identity\ApiTokenId;

final class ApiTokenIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return ApiTokenId::class;
    }
}
