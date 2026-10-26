<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

/** `priceDeltaCents` pode ser 0 ou negativo ("Sem pão" −200). */
final readonly class ModifierRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 80)] public string $name = '',
        public int $priceDeltaCents = 0,
    ) {
    }
}
