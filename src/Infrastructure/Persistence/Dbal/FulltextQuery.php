<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dbal;

/**
 * Traduz o que a pessoa digitou para o modo booleano do FULLTEXT do MySQL:
 *   "cho"         → "+cho*"            (prefixo: acha "Chopp")
 *   "x bur bacon" → "+bur* +bacon*"    (todas as palavras; "x" < 3 letras sai)
 *
 * Palavras com menos de 3 letras não entram: o InnoDB não indexa tokens menores
 * (`innodb_ft_min_token_size` = 3) e elas fariam a busca não achar nada.
 * Os operadores do modo booleano (+ - < > ( ) ~ * " @) são removidos: a entrada
 * é texto do usuário, não sintaxe.
 */
final class FulltextQuery
{
    public const MIN_WORD = 3;

    /** @return string|null `null` = nenhuma palavra pesquisável (não consultar o banco) */
    public static function fromUserInput(string $input): ?string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($input), flags: \PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_filter($words, static fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD);

        if ([] === $words) {
            return null;
        }

        return implode(' ', array_map(static fn (string $word): string => "+{$word}*", array_unique($words)));
    }
}
