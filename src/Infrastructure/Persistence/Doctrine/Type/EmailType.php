<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Identity\Email;

/** @extends StringValueType<Email> */
final class EmailType extends StringValueType
{
    protected function length(): int
    {
        return 190;
    }

    protected function fromString(string $value): Email
    {
        return Email::fromString($value);
    }

    protected function toString(object $value): string
    {
        return $value->toString();
    }

    protected function valueClass(): string
    {
        return Email::class;
    }
}
