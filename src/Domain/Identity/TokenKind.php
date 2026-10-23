<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/** Prefixo do token em claro: diz em qual tabela procurar sem tentar as duas. */
enum TokenKind: string
{
    case User = 'u';
    case Device = 'd';
}
