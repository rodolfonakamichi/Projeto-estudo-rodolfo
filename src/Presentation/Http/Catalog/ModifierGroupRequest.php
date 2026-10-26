<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

/** Coerência de min/max entre si e com as opções é regra do Domain (422 com a mensagem dele). */
final readonly class ModifierGroupRequest
{
    /** @param list<ModifierRequest> $modifiers */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 80)] public string $name = '',
        public int $minSelect = 0,
        public int $maxSelect = 1,
        #[Assert\Valid] public array $modifiers = [],
    ) {
    }
}
