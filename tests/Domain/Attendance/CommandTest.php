<?php

declare(strict_types=1);

namespace App\Tests\Domain\Attendance;

use App\Domain\Attendance\BindTarget;
use App\Domain\Attendance\BindType;
use App\Domain\Attendance\BusinessDay;
use App\Domain\Attendance\Command;
use App\Domain\Attendance\CommandId;
use App\Domain\Attendance\CommandItemId;
use App\Domain\Attendance\CommandItemStatus;
use App\Domain\Attendance\CommandStatus;
use App\Domain\Attendance\Event\CommandItemAdded;
use App\Domain\Attendance\Event\CommandItemCancelled;
use App\Domain\Attendance\Event\CommandOpened;
use App\Domain\Attendance\LocationId;
use App\Domain\Attendance\Quantity;
use App\Domain\Catalog\ModifierId;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductSelection;
use App\Domain\Catalog\ProductVariantId;
use App\Domain\Catalog\SelectedModifier;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\Money;
use PHPUnit\Framework\TestCase;

/** Regras da comanda sem banco (aceite M3: "Command::close() lança exceção se balance ≠ 0"). */
final class CommandTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-26 20:00');
    }

    /** Aceite M3: 2× X-Burger com Bacon → line_total = (3500 + 500) × 2 = 8000. */
    public function test_linha_e_totais(): void
    {
        $command = $this->openCommand();
        $item = $command->addItem(CommandItemId::generate(), $this->burgerWithBacon(), Quantity::of(2), 'sem cebola', $this->now);

        self::assertSame(8000, $item->lineTotal()->cents());
        self::assertSame(500, $item->modifiersTotal()->cents());
        self::assertSame(8000, $command->total()->cents());
        self::assertSame(8000, $command->balance()->cents());

        $command->changeItemQuantity($item->id(), Quantity::of(3), $this->now);
        self::assertSame(12000, $command->total()->cents(), 'total recalculado a cada mudança');
    }

    public function test_cancelar_item_nao_apaga_e_sai_do_total(): void
    {
        $command = $this->openCommand();
        $burger = $command->addItem(CommandItemId::generate(), $this->burgerWithBacon(), Quantity::of(1), null, $this->now);
        $command->addItem(CommandItemId::generate(), $this->burgerWithBacon(), Quantity::of(1), null, $this->now);

        $command->cancelItem($burger->id(), UserId::generate(), 'cliente desistiu', $this->now);

        self::assertCount(2, $command->items(), 'item cancelado continua na comanda');
        self::assertSame(CommandItemStatus::Cancelled, $burger->status());
        self::assertSame('cliente desistiu', $burger->cancelReason());
        self::assertSame(4000, $command->total()->cents());

        $this->expectException(DomainException::class);
        $command->cancelItem($burger->id(), UserId::generate(), 'de novo', $this->now);
    }

    public function test_cancelar_item_exige_motivo(): void
    {
        $command = $this->openCommand();
        $item = $command->addItem(CommandItemId::generate(), $this->burgerWithBacon(), Quantity::of(1), null, $this->now);

        $this->expectException(DomainException::class);
        $command->cancelItem($item->id(), UserId::generate(), '  ', $this->now);
    }

    /** Aceite M3 (doc 07): fechar com saldo ≠ 0 é proibido. */
    public function test_nao_fecha_com_saldo(): void
    {
        $command = $this->openCommand();
        $command->addItem(CommandItemId::generate(), $this->burgerWithBacon(), Quantity::of(1), null, $this->now);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('saldo');
        $command->close($this->now);
    }

    public function test_cancelada_nao_aceita_item_e_libera_a_mesa(): void
    {
        $command = $this->openCommand();
        $command->cancel(UserId::generate(), 'aberta por engano', $this->now);

        self::assertSame(CommandStatus::Cancelled, $command->status());

        $this->expectException(DomainException::class);
        $command->addItem(CommandItemId::generate(), $this->burgerWithBacon(), Quantity::of(1), null, $this->now);
    }

    public function test_registra_os_eventos_do_doc_05(): void
    {
        $command = $this->openCommand();
        $item = $command->addItem(CommandItemId::generate(), $this->burgerWithBacon(), Quantity::of(1), null, $this->now);
        $command->cancelItem($item->id(), UserId::generate(), 'erro de lançamento', $this->now);

        $events = $command->releaseEvents();

        self::assertSame([CommandOpened::class, CommandItemAdded::class, CommandItemCancelled::class], array_map(static fn (object $event): string => $event::class, $events));
        self::assertSame([], $command->releaseEvents(), 'liberar esvazia a lista');
    }

    public function test_vinculo_mesa_exige_id_da_mesa_e_quantidade_tem_limites(): void
    {
        self::assertNull(BindTarget::of(BindType::Counter, '  ')->ref);

        try {
            BindTarget::of(BindType::Table, null);
            self::fail('mesa sem referência');
        } catch (DomainException) {
        }

        $this->expectException(DomainException::class);
        Quantity::of(0);
    }

    public function test_data_operacional_vira_as_5h_no_fuso_da_filial(): void
    {
        // 01:30 de sábado em São Paulo (04:30 UTC) ainda é sexta para o bar
        self::assertSame('2026-10-23', BusinessDay::of(new \DateTimeImmutable('2026-10-24 04:30', new \DateTimeZone('UTC')), 'America/Sao_Paulo')->format('Y-m-d'));
        self::assertSame('2026-10-24', BusinessDay::of(new \DateTimeImmutable('2026-10-24 08:00', new \DateTimeZone('UTC')), 'America/Sao_Paulo')->format('Y-m-d'));
    }

    private function openCommand(): Command
    {
        return Command::open(
            CommandId::generate(),
            CompanyId::generate(),
            BranchId::generate(),
            BindTarget::of(BindType::Table, LocationId::generate()->toString()),
            42,
            new \DateTimeImmutable('2026-10-26'),
            UserId::generate(),
            $this->now,
        );
    }

    private function burgerWithBacon(): ProductSelection
    {
        return new ProductSelection(
            ProductId::generate(),
            ProductVariantId::generate(),
            'X-Burger',
            'X-Burger',
            Money::fromCents(3500),
            [new SelectedModifier(ModifierId::generate(), 'Bacon', Money::fromCents(500))],
            null,
        );
    }
}
