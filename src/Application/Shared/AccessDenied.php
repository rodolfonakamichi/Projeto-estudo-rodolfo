<?php

declare(strict_types=1);

namespace App\Application\Shared;

/** Autenticado, mas sem permissão para a ação (HTTP 403). Lançada pelos casos de uso. */
final class AccessDenied extends \RuntimeException
{
    public static function missing(string $permission): self
    {
        return new self("Sem permissão: {$permission}.");
    }
}
