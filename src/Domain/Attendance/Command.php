<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Domain\Attendance\Event\CommandCancelled;
use App\Domain\Attendance\Event\CommandItemAdded;
use App\Domain\Attendance\Event\CommandItemCancelled;
use App\Domain\Attendance\Event\CommandItemChanged;
use App\Domain\Attendance\Event\CommandOpened;
use App\Domain\Catalog\ProductSelection;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use App\Domain\Shared\AggregateRoot;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\Money;
use App\Domain\Shared\NotFound;
use App\Domain\Shared\RecordsEvents;
use App\Domain\Shared\Timestamped;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Comanda — o agregado mais importante (doc 02 §3.1): acumula o consumo de um
 * vínculo (mesa, balcão, pulseira...) do abrir ao fechar e controla o total.
 *
 * Invariantes que ESTA classe garante:
 *   1. total = subtotal − descontos (+ taxa); saldo = total − pago — recalculados a cada mudança;
 *   2. só lança item com a comanda ativa (aguardando pagamento volta para OPEN);
 *   3. não fecha com saldo ≠ 0;
 *   4. cancelar exige OPEN e nada pago;
 *   5. item nunca é apagado: cancela com motivo e autor.
 * Os estados seguem a máquina do doc 04 — ninguém muda `status` por fora.
 *
 * `openTableRef` = a mesa enquanto a comanda a ocupa (null fora disso): com
 * `UNIQUE (branch_id, open_table_ref)` o banco garante uma comanda aberta por
 * mesa mesmo se dois garçons abrirem ao mesmo tempo.
 */
class Command implements AggregateRoot, Timestamped
{
    use RecordsEvents;

    private const MAX_REASON = 255;

    private CommandStatus $status = CommandStatus::Open;
    private ?string $openTableRef;
    private ?\DateTimeImmutable $closedAt = null;
    private ?string $cancelReason = null;
    private Money $subtotal;
    private Money $discountTotal;
    private Money $serviceFee;
    private Money $total;
    private Money $paidTotal;
    private Money $balance;
    /** @var Collection<int, CommandItem> */
    private Collection $items;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        private CommandId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly CompanyId $companyId,
        private readonly BranchId $branchId,
        private readonly int $displayNumber,
        private readonly \DateTimeImmutable $businessDate,
        private readonly BindType $bindType,
        private readonly ?string $bindRef,
        private readonly UserId $openedBy,
        private readonly \DateTimeImmutable $openedAt,
    ) {
        $this->items = new ArrayCollection();
        $this->openTableRef = BindType::Table === $bindType ? $bindRef : null;
        $this->subtotal = $this->discountTotal = $this->serviceFee = $this->total = $this->paidTotal = $this->balance = Money::fromCents(0);
    }

    /**
     * "Mesa livre?" e o número do dia vêm do caso de uso (precisam do banco);
     * aqui nasce a comanda e o evento.
     */
    public static function open(
        CommandId $id,
        CompanyId $companyId,
        BranchId $branchId,
        BindTarget $bind,
        int $displayNumber,
        \DateTimeImmutable $businessDate,
        UserId $openedBy,
        \DateTimeImmutable $now,
    ): self {
        if ($displayNumber < 1) {
            throw new DomainException('Número da comanda inválido.');
        }

        $command = new self($id, $companyId, $branchId, $displayNumber, $businessDate, $bind->type, $bind->ref, $openedBy, $now);
        $command->record(new CommandOpened($id->toString(), $displayNumber, $bind->type->value, $bind->ref, $openedBy->toString(), $now));

        return $command;
    }

    // ── itens ───────────────────────────────────────────────────────────

    public function addItem(CommandItemId $itemId, ProductSelection $selection, Quantity $quantity, ?string $notes, UserId $by, \DateTimeImmutable $now): CommandItem
    {
        if (!$this->status->isActive()) {
            throw new DomainException("A comanda #{$this->displayNumber} está {$this->status->value}: não aceita item.");
        }
        // doc 04: lançar em comanda aguardando pagamento a devolve para OPEN
        $this->status = CommandStatus::Open;

        $item = CommandItem::fromSelection($itemId, $this, $selection, $quantity, $notes);
        $this->items->add($item);
        $this->recalculate();

        $this->record(new CommandItemAdded(
            $this->id->toString(),
            $this->displayNumber,
            $itemId->toString(),
            $selection->productId->toString(),
            $selection->variantId->toString(),
            $item->displayName(),
            $quantity->value,
            $selection->unitPrice->cents(),
            array_map(static fn ($modifier): array => [
                'modifierId' => $modifier->modifierId->toString(),
                'name' => $modifier->name,
                'priceDeltaCents' => $modifier->priceDelta->cents(),
            ], $selection->modifiers),
            $item->lineTotal()->cents(),
            $by->toString(),
            $now,
        ));

        return $item;
    }

    public function changeItemQuantity(CommandItemId $itemId, Quantity $quantity, UserId $by, \DateTimeImmutable $now): void
    {
        $this->assertOpen();
        $item = $this->item($itemId);
        $old = $item->quantity();
        if ($old === $quantity->value) {
            return;
        }

        $item->changeQuantity($quantity);
        $this->recalculate();
        $this->record(new CommandItemChanged($this->id->toString(), $this->displayNumber, $itemId->toString(), $item->displayName(), $old, $quantity->value, $by->toString(), $now));
    }

    public function cancelItem(CommandItemId $itemId, UserId $by, string $reason, \DateTimeImmutable $now): void
    {
        $this->assertOpen();
        $reason = self::assertReason($reason);

        $item = $this->item($itemId);
        $item->cancel($by, $reason, $now);
        $this->recalculate();
        $this->record(new CommandItemCancelled($this->id->toString(), $this->displayNumber, $itemId->toString(), $item->displayName(), $item->quantity(), $reason, $by->toString(), $now));
    }

    public function item(CommandItemId $itemId): CommandItem
    {
        foreach ($this->items as $item) {
            if ($item->id()->equals($itemId)) {
                return $item;
            }
        }

        throw new NotFound("Item não encontrado na comanda #{$this->displayNumber}.");
    }

    // ── ciclo de vida ───────────────────────────────────────────────────

    /** Cancelar ≠ estornar: só sem nada pago (doc 04). Libera a mesa. */
    public function cancel(UserId $by, string $reason, \DateTimeImmutable $now): void
    {
        $this->assertOpen();
        if (!$this->paidTotal->isZero()) {
            throw new DomainException('A comanda tem pagamento: cancelar exige estorno antes.');
        }

        $this->cancelReason = self::assertReason($reason);
        $this->status = CommandStatus::Cancelled;
        $this->closedAt = $now;
        $this->openTableRef = null;
        $this->record(new CommandCancelled($this->id->toString(), $this->displayNumber, $this->cancelReason, $by->toString(), $now));
    }

    /** Só fecha paga e com saldo zero (doc 02, invariante 3). Pagamentos chegam no M5. */
    public function close(\DateTimeImmutable $now): void
    {
        if (!$this->balance->isZero()) {
            throw new DomainException(\sprintf('A comanda #%d não fecha com saldo de %d centavos.', $this->displayNumber, $this->balance->cents()));
        }
        if (CommandStatus::Paid !== $this->status) {
            throw new DomainException("A comanda #{$this->displayNumber} precisa estar paga para fechar.");
        }

        $this->status = CommandStatus::Closed;
        $this->closedAt = $now;
        $this->openTableRef = null;
    }

    // ── leitura ─────────────────────────────────────────────────────────

    public function id(): CommandId
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

    public function displayNumber(): int
    {
        return $this->displayNumber;
    }

    public function businessDate(): \DateTimeImmutable
    {
        return $this->businessDate;
    }

    public function bindType(): BindType
    {
        return $this->bindType;
    }

    public function bindRef(): ?string
    {
        return $this->bindRef;
    }

    public function status(): CommandStatus
    {
        return $this->status;
    }

    public function openedBy(): UserId
    {
        return $this->openedBy;
    }

    public function subtotal(): Money
    {
        return $this->subtotal;
    }

    public function total(): Money
    {
        return $this->total;
    }

    public function balance(): Money
    {
        return $this->balance;
    }

    /** @return list<CommandItem> inclusive os cancelados */
    public function items(): array
    {
        return array_values($this->items->toArray());
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    /** Invariante 1: totais sempre derivados dos itens ativos (desconto no M3.4, taxa e pagamento no M5). */
    private function recalculate(): void
    {
        $this->subtotal = array_reduce(
            array_filter($this->items(), static fn (CommandItem $item): bool => $item->isActive()),
            static fn (Money $sum, CommandItem $item): Money => $sum->add($item->lineTotal()),
            Money::fromCents(0),
        );
        $this->total = $this->subtotal->subtract($this->discountTotal)->add($this->serviceFee);
        $this->balance = $this->total->subtract($this->paidTotal);
    }

    private function assertOpen(): void
    {
        if (CommandStatus::Open !== $this->status) {
            throw new DomainException("A comanda #{$this->displayNumber} não está aberta ({$this->status->value}).");
        }
    }

    private static function assertReason(string $reason): string
    {
        $reason = trim($reason);
        if ('' === $reason || mb_strlen($reason) > self::MAX_REASON) {
            throw new DomainException(\sprintf('Informe o motivo (até %d caracteres).', self::MAX_REASON));
        }

        return $reason;
    }
}
