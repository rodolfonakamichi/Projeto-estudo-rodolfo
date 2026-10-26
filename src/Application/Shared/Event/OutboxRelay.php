<?php

declare(strict_types=1);

namespace App\Application\Shared\Event;

/**
 * Porta: despacha os eventos pendentes do outbox, em ordem, um por transação.
 * Para no primeiro que falhar (a ordem importa) — ele fica pendente e a
 * próxima rodada tenta de novo.
 */
interface OutboxRelay
{
    public function relay(int $limit): RelayResult;
}
