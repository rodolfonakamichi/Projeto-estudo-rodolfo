<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Raiz de agregado que REGISTRA o que aconteceu (doc 05): o método de negócio
 * chama `record()`; quem grava o agregado chama `releaseEvents()` e leva os
 * eventos para o outbox na mesma transação (M3.3).
 *
 * Não persiste: a lista é só memória até ser liberada.
 */
trait RecordsEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /** @return list<DomainEvent> e esvazia a lista */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function record(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
