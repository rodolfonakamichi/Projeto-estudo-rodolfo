<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Outbox;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Shared\Ulid;

/**
 * Uma linha do outbox (`domain_events`, doc 05): o evento de domínio já como
 * DADOS, com o endereço do agregado. Entidade do Doctrine de propósito: criada
 * no onFlush, entra no MESMO INSERT/COMMIT da mudança de estado — não há como
 * gravar a comanda e perder o evento (ou o contrário).
 *
 * Fica na Infrastructure: o Domain só conhece DomainEvent; como ele é guardado
 * é detalhe de persistência.
 */
class StoredEvent
{
    private string $id;
    private ?\DateTimeImmutable $publishedAt = null;

    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly CompanyId $companyId,
        private readonly ?BranchId $branchId,
        private readonly string $aggregateType,
        private readonly string $aggregateId,
        private readonly string $name,
        private readonly array $payload,
        private readonly \DateTimeImmutable $occurredAt,
    ) {
        // ULID: ordena por criação — é a ordem de despacho
        $this->id = Ulid::generate()->toString();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
    }
}
