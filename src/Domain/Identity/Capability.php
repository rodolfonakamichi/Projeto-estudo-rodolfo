<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Recursos que uma empresa pode ligar (ADR-001). O core nunca pergunta "que
 * tipo de negócio é este?" — pergunta "esta empresa tem a capability X?".
 * No MVP só `Tables`, `Kitchen` e `Inventory` têm módulo; as demais são reservadas.
 */
enum Capability: string
{
    case Tables = 'tables';
    case Kitchen = 'kitchen';
    case Delivery = 'delivery';
    case Appointment = 'appointment';
    case Commission = 'commission';
    case Cashless = 'cashless';
    case Inventory = 'inventory';
    case Hotel = 'hotel';
}
