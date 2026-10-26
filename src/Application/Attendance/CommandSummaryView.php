<?php

declare(strict_types=1);

namespace App\Application\Attendance;

/** Linha da lista de comandas abertas (tela do salão). */
final readonly class CommandSummaryView
{
    public function __construct(
        public string $id,
        public int $displayNumber,
        public string $bindType,
        public ?string $bindRef,
        public ?string $bindLabel,
        public string $status,
        public int $totalCents,
        public int $activeItems,
        public string $openedAt,
    ) {
    }
}
