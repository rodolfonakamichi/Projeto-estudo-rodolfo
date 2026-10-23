<?php

declare(strict_types=1);

namespace App\Tests\Domain\Identity;

use App\Domain\Identity\Slug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function test_aceita_minusculas_digitos_e_hifen(): void
    {
        self::assertSame('bar-do-ze-2', Slug::fromString('bar-do-ze-2')->toString());
    }

    #[DataProvider('invalidos')]
    public function test_rejeita_formato_invalido(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Slug::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidos(): iterable
    {
        yield 'vazio' => [''];
        yield 'um caractere' => ['a'];
        yield 'maiúscula' => ['Bar'];
        yield 'espaço' => ['bar do ze'];
        yield 'acento' => ['bar-do-zé'];
        yield 'hífen na ponta' => ['-bar'];
        yield 'hífen duplo' => ['bar--ze'];
        yield 'longo demais' => [str_repeat('a', 81)];
    }
}
