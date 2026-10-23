<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Percentual de 0 a 100 com 2 casas, guardado em centésimos (inteiro) — como
 * Money em centavos: nada de float em regra de negócio. 12,5% = 1250.
 */
final readonly class Percentage
{
    private function __construct(private int $hundredths)
    {
        if ($hundredths < 0 || $hundredths > 10000) {
            throw new \InvalidArgumentException('Percentual deve estar entre 0 e 100.');
        }
    }

    public static function fromHundredths(int $hundredths): self
    {
        return new self($hundredths);
    }

    /** Aceita "5", "12.5", "12.50" (formato do DECIMAL do banco). */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', trim($value), $m)) {
            throw new \InvalidArgumentException("Percentual inválido: \"{$value}\"");
        }

        return new self((int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0'));
    }

    public function hundredths(): int
    {
        return $this->hundredths;
    }

    /** "12.50" — formato do DECIMAL(5,2). */
    public function toString(): string
    {
        return \sprintf('%d.%02d', intdiv($this->hundredths, 100), $this->hundredths % 100);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->hundredths > $other->hundredths;
    }

    public function equals(self $other): bool
    {
        return $this->hundredths === $other->hundredths;
    }
}
