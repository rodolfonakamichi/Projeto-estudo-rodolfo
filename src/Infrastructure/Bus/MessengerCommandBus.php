<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus;

use App\Application\Shared\Bus\Command;
use App\Application\Shared\Bus\CommandBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adapter: implementa a porta CommandBus (Application) com o Symfony Messenger.
 * Trocar o Messenger por outra lib = escrever outro adapter; os casos de uso
 * não mudam (inversão de dependência).
 */
final readonly class MessengerCommandBus implements CommandBus
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $bus,
    ) {
    }

    public function dispatch(Command $command): void
    {
        try {
            $this->bus->dispatch($command);
        } catch (HandlerFailedException $e) {
            // O Messenger embrulha a exceção do handler; devolvemos a original
            // para que exceções de domínio cheguem intactas a quem chamou.
            throw $e->getPrevious() ?? $e;
        }
    }
}
