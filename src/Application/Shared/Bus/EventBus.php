<?php

declare(strict_types=1);

namespace App\Application\Shared\Bus;

use App\Domain\Shared\DomainEvent;

interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
