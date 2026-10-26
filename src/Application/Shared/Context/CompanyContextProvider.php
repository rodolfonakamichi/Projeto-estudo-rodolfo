<?php

declare(strict_types=1);

namespace App\Application\Shared\Context;

use App\Domain\Identity\CompanyContext;

/**
 * Porta: "de qual empresa é esta requisição?". Casos de uso que criam dados
 * leem o `companyId` daqui, nunca do payload do cliente.
 *
 * Só leitura de propósito: quem ENTRA no contexto é a autenticação
 * (Infrastructure), não a Application.
 */
interface CompanyContextProvider
{
    /** @throws \LogicException sem contexto (rota pública, CLI) */
    public function get(): CompanyContext;

    public function has(): bool;
}
