<?php

declare(strict_types=1);

namespace Tests\Domain\Shared;

use App\Domain\Shared\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_cria_a_partir_de_centavos(): void
    {
        $m = Money::fromCents(2599);

        self::assertSame(2599, $m->cents());
        self::assertSame('BRL', $m->currency());
    }

    public function test_soma_dois_valores_da_mesma_moeda(): void
    {
        $total = Money::fromCents(2599)->add(Money::fromCents(1));

        self::assertSame(2600, $total->cents());
    }

    public function test_somar_moedas_diferentes_lanca_erro(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromCents(100, 'BRL')->add(Money::fromCents(100, 'USD'));
    }

    public function test_igualdade_compara_valor_e_moeda(): void
    {
        self::assertTrue(Money::fromCents(500)->equals(Money::fromCents(500)));
        self::assertFalse(Money::fromCents(500)->equals(Money::fromCents(501)));
        self::assertFalse(Money::fromCents(500, 'BRL')->equals(Money::fromCents(500, 'USD')));
    }

    public function test_allocate_divide_em_partes_iguais_sem_perder_centavo(): void
    {
        $partes = Money::fromCents(10000)->allocate([1, 1, 1]);

        self::assertSame([3334, 3333, 3333], array_map(fn (Money $m) => $m->cents(), $partes));
        self::assertSame(10000, array_sum(array_map(fn (Money $m) => $m->cents(), $partes)));
    }

    public function test_allocate_respeita_pesos_diferentes(): void
    {
        $partes = Money::fromCents(10000)->allocate([70, 30]);

        self::assertSame([7000, 3000], array_map(fn (Money $m) => $m->cents(), $partes));
    }

    public function test_allocate_mantem_a_moeda(): void
    {
        $partes = Money::fromCents(100, 'USD')->allocate([1, 1]);

        self::assertSame('USD', $partes[0]->currency());
    }

    public function test_add_nao_altera_o_original(): void
    {
        $original = Money::fromCents(1000);
        $original->add(Money::fromCents(500));

        self::assertSame(1000, $original->cents()); // imutável: add() só retorna novo
    }
}