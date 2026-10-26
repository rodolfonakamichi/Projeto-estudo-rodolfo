<?php

declare(strict_types=1);

namespace App\Application\Catalog;

final readonly class StationView
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
    ) {
    }
}
