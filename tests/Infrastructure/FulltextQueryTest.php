<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure;

use App\Infrastructure\Persistence\Dbal\FulltextQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FulltextQueryTest extends TestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function entradas(): iterable
    {
        yield 'prefixo simples' => ['cho', '+cho*'];
        yield 'várias palavras, todas obrigatórias' => ['X Bur  bacon', '+bur* +bacon*'];
        yield 'acentos preservados' => ['açaí', '+açaí*'];
        yield 'operadores do usuário são removidos' => ['-chopp +"bar" (cho*)', '+chopp* +bar* +cho*'];
        yield 'repetida conta uma vez' => ['cho cho', '+cho*'];
        yield 'só palavras curtas' => ['x ab', null];
        yield 'vazio' => ['   ', null];
    }

    #[DataProvider('entradas')]
    public function test_traduz_a_entrada_do_usuario(string $input, ?string $expected): void
    {
        self::assertSame($expected, FulltextQuery::fromUserInput($input));
    }
}
