<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Raiz de agregado que registra eventos (usa o trait RecordsEvents). Ao gravar,
 * a infraestrutura libera os eventos e os grava no outbox na MESMA transação,
 * com o "endereço" do agregado (tipo, id, empresa, filial).
 */
interface AggregateRoot
{
    public function id(): Identifier;

    public function companyId(): Identifier;

    /** `null` = agregado da empresa toda, sem filial. */
    public function branchId(): ?Identifier;

    /** @return list<DomainEvent> e esvazia a lista */
    public function releaseEvents(): array;
}
