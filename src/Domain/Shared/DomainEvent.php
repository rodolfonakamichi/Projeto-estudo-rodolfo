<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Algo que aconteceu no domínio, nomeado no particípio (`OrderItemAdded`).
 * Imutável. Catálogo completo no doc 05.
 */
interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;
}
