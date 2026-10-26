<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Domain\Shared\DomainException;

/** Quantidade de um item: inteira no MVP (decisão 2026-10-26), de 1 a 999. */
final readonly class Quantity
{
    private const MAX = 999;

    private function __construct(public int $value)
    {
    }

    public static function of(int $value): self
    {
        if ($value < 1 || $value > self::MAX) {
            throw new DomainException(\sprintf('A quantidade deve ser de 1 a %d.', self::MAX));
        }

        return new self($value);
    }
}
