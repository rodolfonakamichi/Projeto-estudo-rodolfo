<?php

declare(strict_types=1);

namespace App\Application\Shared\Bus;

/**
 * Reage a um DomainEvent: `__invoke(XEvent $event)`. Um evento pode ter 0..N handlers.
 */
interface EventHandler
{
}
