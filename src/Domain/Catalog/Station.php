<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\Timestamped;

/**
 * Estação de produção (`COZINHA`, `BAR`): para onde o pedido é roteado e o que
 * o KDS mostra. Agregado próprio, da filial. Só existe com a capability `kitchen`
 * (quem garante é o caso de uso, que conhece a empresa).
 *
 * Empresa e filial por ID, não por objeto (doc 02): o Catálogo não depende das
 * entidades de Identity, só dos IDs tipados.
 */
class Station implements Timestamped
{
    private const MAX_NAME = 60;

    private bool $active = true;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        private StationId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly CompanyId $companyId,
        private readonly BranchId $branchId,
        private string $name,
    ) {
    }

    /** Unicidade do nome na filial é do caso de uso (precisa consultar o repositório). */
    public static function create(StationId $id, CompanyId $companyId, BranchId $branchId, string $name): self
    {
        return new self($id, $companyId, $branchId, self::assertName($name));
    }

    public function rename(string $name): void
    {
        $this->name = self::assertName($name);
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function id(): StationId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function branchId(): BranchId
    {
        return $this->branchId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    private static function assertName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_NAME) {
            throw new DomainException(\sprintf('Nome da estação deve ter entre 1 e %d caracteres.', self::MAX_NAME));
        }

        return $name;
    }
}
