<?php

declare(strict_types=1);

namespace App\Application\Shared\Bus;

/**
 * Caso de uso: um handler por Command, com um único método `__invoke(XCommand $command)`.
 * Implementar esta interface já registra o handler no command.bus (config/services.yaml).
 */
interface CommandHandler
{
}
