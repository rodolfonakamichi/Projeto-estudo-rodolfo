<?php

declare(strict_types=1);

namespace App\Application\Catalog;

final readonly class ModifierGroupView
{
    /** @param list<ModifierView> $modifiers */
    public function __construct(
        public string $id,
        public string $name,
        public int $minSelect,
        public int $maxSelect,
        public bool $required,
        public array $modifiers,
    ) {
    }
}
