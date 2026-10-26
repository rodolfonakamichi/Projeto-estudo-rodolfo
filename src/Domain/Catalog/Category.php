<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\Timestamped;

/**
 * Categoria do cardápio da filial (`Bebidas`, `Lanches`). Agregado próprio:
 * o produto aponta para ela por ID.
 *
 * Não é apagada, é ARQUIVADA: some do cardápio, mas o histórico (relatórios,
 * comandas antigas) continua coerente.
 */
class Category implements Timestamped
{
    private const MAX_NAME = 120;

    private ?\DateTimeImmutable $archivedAt = null;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        private CategoryId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly CompanyId $companyId,
        private readonly BranchId $branchId,
        private string $name,
        private int $sortOrder,
    ) {
    }

    /** Unicidade do nome na filial é do caso de uso (precisa consultar o repositório). */
    public static function create(CategoryId $id, CompanyId $companyId, BranchId $branchId, string $name, int $sortOrder = 0): self
    {
        return new self($id, $companyId, $branchId, self::assertName($name), self::assertSortOrder($sortOrder));
    }

    public function rename(string $name): void
    {
        $this->assertNotArchived();
        $this->name = self::assertName($name);
    }

    /** Posição no cardápio (menor aparece antes). */
    public function moveTo(int $sortOrder): void
    {
        $this->assertNotArchived();
        $this->sortOrder = self::assertSortOrder($sortOrder);
    }

    /** O Domain não lê relógio: quem chama informa o "agora". */
    public function archive(\DateTimeImmutable $now): void
    {
        $this->assertNotArchived();
        $this->archivedAt = $now;
    }

    public function id(): CategoryId
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

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    private function assertNotArchived(): void
    {
        if ($this->isArchived()) {
            throw new DomainException("A categoria \"{$this->name}\" está arquivada.");
        }
    }

    private static function assertName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_NAME) {
            throw new DomainException(\sprintf('Nome da categoria deve ter entre 1 e %d caracteres.', self::MAX_NAME));
        }

        return $name;
    }

    private static function assertSortOrder(int $sortOrder): int
    {
        if ($sortOrder < 0) {
            throw new DomainException('A ordem da categoria não pode ser negativa.');
        }

        return $sortOrder;
    }
}
