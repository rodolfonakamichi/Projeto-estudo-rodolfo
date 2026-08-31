<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Guarda o CompanyContext da requisição atual. O middleware de autenticação
 * (M1.f) chama `set()`; os repositórios chamam `get()` na hora da query.
 *
 * Mutável de propósito: o container constrói os serviços uma vez, mas o
 * contexto só é conhecido no meio da requisição. No modelo PHP-FPM (1 processo
 * por requisição) o holder morre com o processo — sem vazamento entre requisições.
 */
final class CompanyContextHolder
{
    private ?CompanyContext $context = null;

    public function set(CompanyContext $context): void
    {
        $this->context = $context;
    }

    public function get(): CompanyContext
    {
        return $this->context
            ?? throw new \RuntimeException('CompanyContext não resolvido — requisição sem autenticação?');
    }

    public function has(): bool
    {
        return $this->context !== null;
    }
}
