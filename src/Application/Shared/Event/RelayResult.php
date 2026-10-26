<?php

declare(strict_types=1);

namespace App\Application\Shared\Event;

final readonly class RelayResult
{
    public function __construct(
        public int $published,
        public ?string $failedEventId = null,
        public ?string $error = null,
    ) {
    }
}
