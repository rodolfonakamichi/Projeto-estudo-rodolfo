<?php

declare(strict_types=1);

namespace App\Application\Shared\Bus;

/**
 * Porta de entrada dos casos de uso. Quem chama (controller, comando CLI) não
 * conhece o handler — só despacha o Command. Cada despacho é transacional.
 */
interface CommandBus
{
    public function dispatch(Command $command): void;
}
