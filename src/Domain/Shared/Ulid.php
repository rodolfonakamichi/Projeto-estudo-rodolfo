<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Symfony\Component\Uid\Ulid as SymfonyUlid;

/**
 * ULID (Universally Unique Lexicographically Sortable Identifier) — 26 chars,
 * ordenável por tempo, gerado pela aplicação (ADR-005). Envolve o `symfony/uid`
 * para o domínio não depender do pacote diretamente.
 */
final class Ulid
{
    private function __construct(private readonly string $value)
    {
    }

    public static function generate(): self
    {
        return new self((string) new SymfonyUlid());
    }

    public static function fromString(string $value): self
    {
        if (! SymfonyUlid::isValid($value)) {
            throw new \InvalidArgumentException("ULID inválido: {$value}");
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

    public function __toString(): string
    {
        return $this->value;
    }
}
