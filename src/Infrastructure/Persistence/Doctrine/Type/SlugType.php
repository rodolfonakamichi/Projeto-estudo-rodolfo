<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Identity\Slug;

/** @extends StringValueType<Slug> */
final class SlugType extends StringValueType
{
    protected function length(): int
    {
        return 80;
    }

    protected function fromString(string $value): Slug
    {
        return Slug::fromString($value);
    }

    protected function toString(object $value): string
    {
        return $value->toString();
    }

    protected function valueClass(): string
    {
        return Slug::class;
    }
}
