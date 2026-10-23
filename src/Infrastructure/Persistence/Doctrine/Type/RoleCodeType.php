<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Identity\RoleCode;

/** @extends StringValueType<RoleCode> */
final class RoleCodeType extends StringValueType
{
    protected function length(): int
    {
        return 30;
    }

    protected function fromString(string $value): RoleCode
    {
        return RoleCode::fromString($value);
    }

    protected function toString(object $value): string
    {
        return $value->toString();
    }

    protected function valueClass(): string
    {
        return RoleCode::class;
    }
}
