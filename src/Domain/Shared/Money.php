<?php

declare(strict_types=1);

namespace App\Domain\Shared;

final readonly class Money
{
    private function __construct(
        private int    $cents,
        private string $currency,
    ) {
    }

    public static function fromCents(int $cents, string $currency = 'BRL'): self
    {
        return new self($cents, $currency);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents - $other->cents, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents
            && $this->currency === $other->currency;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Moedas diferentes: {$this->currency} e {$other->currency}.",
            );
        }
    }

    /**
     * Divide o valor em partes proporcionais aos pesos, sem perder nem criar
     * centavo — a soma das partes é sempre igual ao total.
     *
     * @param  list<int> $ratios pesos (ex.: [1,1,1] = 3 partes iguais; [70,30])
     * @return list<self>
     */
    public function allocate(array $ratios): array
    {
        if ($ratios === []) {
            throw new \InvalidArgumentException('allocate() exige ao menos um peso.');
        }

        $totalRatio = array_sum($ratios);
        if ($totalRatio <= 0) {
            throw new \InvalidArgumentException('A soma dos pesos deve ser positiva.');
        }

        $parts = [];
        $distributed = 0;

        // 1) cada parte recebe a divisão inteira (arredonda para baixo)
        foreach ($ratios as $ratio) {
            if ($ratio < 0) {
                throw new \InvalidArgumentException('Peso não pode ser negativo.');
            }
            $amount = intdiv($this->cents * $ratio, $totalRatio);
            $parts[] = $amount;
            $distributed += $amount;
        }

        // 2) os centavos que sobraram vão 1 a 1, começando pela primeira parte
        $remainder = $this->cents - $distributed;
        for ($i = 0; $i < $remainder; $i++) {
            $parts[$i] += 1;
        }

        // 3) transforma cada valor de volta em Money
        return array_map(
            fn (int $cents): self => new self($cents, $this->currency),
            $parts,
        );
    }
}
