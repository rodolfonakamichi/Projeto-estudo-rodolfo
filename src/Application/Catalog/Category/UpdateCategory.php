<?php

declare(strict_types=1);

namespace App\Application\Catalog\Category;

use App\Application\Shared\Bus\Command;

/** Altera só o que vier preenchido (`null` = mantém). */
final readonly class UpdateCategory implements Command
{
    public function __construct(
        public string $categoryId,
        public string $actorId,
        public ?string $name = null,
        public ?int $sortOrder = null,
    ) {
    }
}
