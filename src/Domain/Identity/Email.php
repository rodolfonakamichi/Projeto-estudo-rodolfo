<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/** E-mail normalizado (trim + minúsculas) — comparação e índice UNIQUE consistentes. */
final readonly class Email
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = mb_strtolower(trim($value));

        if (mb_strlen($value) > 190 || false === filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("E-mail inválido: \"{$value}\"");
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
