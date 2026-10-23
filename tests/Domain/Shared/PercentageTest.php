<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared;

use App\Domain\Shared\Percentage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PercentageTest extends TestCase
{
    #[DataProvider('validos')]
    public function test_le_o_formato_do_decimal(string $input, int $hundredths, string $output): void
    {
        $p = Percentage::fromString($input);

        self::assertSame($hundredths, $p->hundredths());
        self::assertSame($output, $p->toString());
    }

    /** @return iterable<array{string, int, string}> */
    public static function validos(): iterable
    {
        yield ['5', 500, '5.00'];
        yield ['12.5', 1250, '12.50'];
        yield ['12.05', 1205, '12.05'];
        yield ['100.00', 10000, '100.00'];
        yield ['0', 0, '0.00'];
    }

    public function test_rejeita_acima_de_100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Percentage::fromString('100.01');
    }
}
