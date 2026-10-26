<?php

declare(strict_types=1);

namespace App\Application\Health;

/**
 * Verifica se o banco responde. Porta (interface) da Application; a
 * implementação concreta mora em Infrastructure.
 */
interface DatabaseProbe
{
    /**
     * @return string versão do servidor
     *
     * @throws \RuntimeException se o banco estiver inacessível
     */
    public function serverVersion(): string;
}
