<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductSelection;
use App\Domain\Catalog\ProductVariantId;
use App\Domain\Catalog\StationId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\Money;
use App\Domain\Shared\Timestamped;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Linha de consumo da comanda (visão FINANCEIRA, ADR-006). Nome, preço,
 * modificadores e estação são cópias do cardápio no momento do lançamento.
 * Nunca é apagada: cancelada fica com motivo, autor e hora.
 * Parte do agregado Command — muda só através dele.
 */
class CommandItem implements Timestamped
{
    private const MAX_NOTES = 255;

    private CommandItemStatus $status = CommandItemStatus::Active;
    private ?UserId $cancelledBy = null;
    private ?string $cancelReason = null;
    private ?\DateTimeImmutable $cancelledAt = null;
    private Money $modifiersTotal;
    private Money $lineTotal;
    /** @var Collection<int, CommandItemModifier> */
    private Collection $modifiers;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    /** @internal use Command::addItem() */
    public function __construct(
        private CommandItemId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly Command $command,
        private readonly CompanyId $companyId,
        private readonly ProductId $productId,
        private readonly ProductVariantId $variantId,
        private readonly string $productName,
        private readonly string $variantName,
        private readonly Money $unitPrice,
        private readonly ?StationId $stationId,
        private int $quantity,
        private ?string $notes,
    ) {
        $this->notes = self::assertNotes($notes);
        $this->modifiers = new ArrayCollection();
        $this->modifiersTotal = Money::fromCents(0, $unitPrice->currency());
        $this->recalculate();
    }

    /** @internal */
    public static function fromSelection(CommandItemId $id, Command $command, ProductSelection $selection, Quantity $quantity, ?string $notes): self
    {
        $item = new self(
            $id,
            $command,
            $command->companyId(),
            $selection->productId,
            $selection->variantId,
            $selection->productName,
            $selection->variantName,
            $selection->unitPrice,
            $selection->stationId,
            $quantity->value,
            $notes,
        );
        foreach ($selection->modifiers as $modifier) {
            $item->modifiers->add(new CommandItemModifier($item, $modifier->modifierId, $modifier->name, $modifier->priceDelta));
        }
        $item->modifiersTotal = $selection->modifiersTotal();
        $item->recalculate();

        return $item;
    }

    /** @internal use Command::changeItemQuantity() */
    public function changeQuantity(Quantity $quantity): void
    {
        $this->assertActive();
        $this->quantity = $quantity->value;
        $this->recalculate();
    }

    /** @internal use Command::cancelItem() */
    public function cancel(UserId $by, string $reason, \DateTimeImmutable $now): void
    {
        $this->assertActive();
        $this->status = CommandItemStatus::Cancelled;
        $this->cancelledBy = $by;
        $this->cancelReason = $reason;
        $this->cancelledAt = $now;
    }

    public function isActive(): bool
    {
        return CommandItemStatus::Active === $this->status;
    }

    public function id(): CommandItemId
    {
        return $this->id;
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function variantId(): ProductVariantId
    {
        return $this->variantId;
    }

    /** "X-Burger" ou "Chopp 500ml" (variação junto quando difere do produto). */
    public function displayName(): string
    {
        return $this->variantName === $this->productName ? $this->productName : "{$this->productName} {$this->variantName}";
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function modifiersTotal(): Money
    {
        return $this->modifiersTotal;
    }

    public function lineTotal(): Money
    {
        return $this->lineTotal;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function stationId(): ?StationId
    {
        return $this->stationId;
    }

    public function status(): CommandItemStatus
    {
        return $this->status;
    }

    public function cancelReason(): ?string
    {
        return $this->cancelReason;
    }

    /** @return list<CommandItemModifier> */
    public function modifiers(): array
    {
        return array_values($this->modifiers->toArray());
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    /** line_total = (preço + modificadores) × quantidade (doc 03). */
    private function recalculate(): void
    {
        $this->lineTotal = $this->unitPrice->add($this->modifiersTotal)->times($this->quantity);
    }

    private function assertActive(): void
    {
        if (!$this->isActive()) {
            throw new DomainException("O item \"{$this->displayName()}\" já está cancelado.");
        }
    }

    private static function assertNotes(?string $notes): ?string
    {
        $notes = null === $notes ? null : trim($notes);
        if ('' === $notes) {
            return null;
        }
        if (null !== $notes && mb_strlen($notes) > self::MAX_NOTES) {
            throw new DomainException(\sprintf('A observação deve ter no máximo %d caracteres.', self::MAX_NOTES));
        }

        return $notes;
    }
}
