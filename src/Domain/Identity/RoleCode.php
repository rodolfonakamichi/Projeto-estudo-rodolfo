<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Código do papel (`WAITER`, `CASHIER`...): maiúsculas, dígitos e `_`.
 * Papéis são por empresa — além dos padrão (DefaultRoles), a empresa pode criar os seus.
 */
final readonly class RoleCode
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = strtoupper(trim($value));

        if (1 !== preg_match('/^[A-Z][A-Z0-9_]{1,29}$/', $value)) {
            throw new \InvalidArgumentException("Código de papel inválido: \"{$value}\"");
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
