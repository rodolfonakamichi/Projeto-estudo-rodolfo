<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Domain\Identity\BranchId;

interface CommandRepository
{
    /** Grava a comanda inteira (itens e modificadores em cascata). */
    public function save(Command $command): void;

    /** @throws CommandNotFound */
    public function get(CommandId $id): Command;

    /** A comanda que ocupa a mesa agora, se houver. */
    public function openOnTable(BranchId $branchId, LocationId $tableId): ?Command;
}
