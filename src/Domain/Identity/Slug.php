<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Identificador legível da empresa na URL/QR (`bar-do-ze`): minúsculas,
 * dígitos e hífen, 2 a 80 caracteres, sem hífen nas pontas.
 */
final readonly class Slug
{
    private const PATTERN = '/^[a-z0-9][a-z0-9-]{0,78}[a-z0-9]$/';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value) || str_contains($value, '--')) {
            throw new \InvalidArgumentException("Slug inválido: \"{$value}\"");
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
