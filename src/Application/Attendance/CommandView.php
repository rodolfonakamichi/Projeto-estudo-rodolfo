<?php

declare(strict_types=1);

namespace App\Application\Attendance;

/** Comanda completa (tela da comanda). Dinheiro sempre em centavos. */
final readonly class CommandView
{
    /** @param list<CommandItemView> $items inclusive os cancelados */
    public function __construct(
        public string $id,
        public int $displayNumber,
        public string $businessDate,
        public string $bindType,
        public ?string $bindRef,
        public ?string $bindLabel,
        public string $status,
        public string $openedBy,
        public string $openedAt,
        public int $subtotalCents,
        public int $discountTotalCents,
        public int $serviceFeeCents,
        public int $totalCents,
        public int $paidTotalCents,
        public int $balanceCents,
        public array $items,
    ) {
    }
}
