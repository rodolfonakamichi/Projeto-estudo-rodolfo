<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\Ulid;

/**
 * Base dos identificadores tipados (CompanyId, UserId, BranchId...).
 * Cada subclasse é `final class X extends Identifier {}` — a herança serve só
 * para o sistema de tipos: passar um UserId onde se espera um CompanyId não
 * compila.
 *
 * Construtor `final` → `new static(...)` nos métodos estáticos é seguro.
 */
abstract class Identifier
{
    final public function __construct(public readonly Ulid $ulid)
    {
    }

    public static function generate(): static
    {
        return new static(Ulid::generate());
    }

    public static function fromString(string $value): static
    {
        return new static(Ulid::fromString($value));
    }

    public function toString(): string
    {
        return $this->ulid->toString();
    }

    /** Um CompanyId nunca é igual a um UserId, mesmo com o mesmo ULID. */
    public function equals(self $other): bool
    {
        return static::class === $other::class && $this->ulid->equals($other->ulid);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
