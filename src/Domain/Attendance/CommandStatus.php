<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

/** Estados da comanda (doc 04 §1). Quem muda é só o agregado Command (ADR-007). */
enum CommandStatus: string
{
    case Open = 'OPEN';
    case AwaitingPayment = 'AWAITING_PAYMENT';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Closed = 'CLOSED';
    case Cancelled = 'CANCELLED';

    /** Ainda ocupa a mesa e aceita item (aguardando pagamento volta para OPEN ao lançar). */
    public function isActive(): bool
    {
        return \in_array($this, [self::Open, self::AwaitingPayment, self::PartiallyPaid], true);
    }
}
