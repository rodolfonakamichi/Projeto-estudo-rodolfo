<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\Timestamped;

/**
 * Local físico da filial onde se consome: `Mesa 12`, `Balcão`, `Deck`
 * (doc 01: a mesa é o LOCAL; a comanda é o consumo). A comanda de mesa aponta
 * para cá por ID — e só pode haver uma comanda aberta por mesa.
 *
 * O QR code (cliente pede pelo celular) entra no milestone do QR.
 */
class Location implements Timestamped
{
    private const MAX_LABEL = 60;

    private bool $active = true;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        private LocationId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly CompanyId $companyId,
        private readonly BranchId $branchId,
        private readonly LocationType $type,
        private string $label,
    ) {
    }

    /** Rótulo único na filial e capability do tipo são do caso de uso (precisam de repositório/empresa). */
    public static function create(LocationId $id, CompanyId $companyId, BranchId $branchId, LocationType $type, string $label): self
    {
        return new self($id, $companyId, $branchId, $type, self::assertLabel($label));
    }

    public function relabel(string $label): void
    {
        $this->label = self::assertLabel($label);
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function id(): LocationId
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

    public function type(): LocationType
    {
        return $this->type;
    }

    public function label(): string
    {
        return $this->label;
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

    private static function assertLabel(string $label): string
    {
        $label = trim($label);
        if ('' === $label || mb_strlen($label) > self::MAX_LABEL) {
            throw new DomainException(\sprintf('Nome do local deve ter entre 1 e %d caracteres.', self::MAX_LABEL));
        }

        return $label;
    }
}
