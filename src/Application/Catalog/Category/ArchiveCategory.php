<?php

declare(strict_types=1);

namespace App\Application\Catalog\Category;

use App\Application\Shared\Bus\Command;

/** Tira a categoria do cardápio sem apagar (o histórico continua apontando para ela). */
final readonly class ArchiveCategory implements Command
{
    public function __construct(
        public string $categoryId,
        public string $actorId,
    ) {
    }
}
