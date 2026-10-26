<?php

declare(strict_types=1);

namespace App\Application\Attendance;

final readonly class LocationView
{
    public function __construct(
        public string $id,
        public string $type,
        public string $label,
        public bool $active,
    ) {
    }
}
