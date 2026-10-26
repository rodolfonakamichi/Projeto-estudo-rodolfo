<?php

declare(strict_types=1);

namespace App\Presentation\Http\Attendance;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddItemRequest
{
    /** @param list<string> $modifierIds */
    public function __construct(
        #[Assert\NotBlank, Assert\Ulid] public string $productId = '',
        #[Assert\NotBlank, Assert\Ulid] public string $variantId = '',
        #[Assert\Range(min: 1, max: 999)] public int $quantity = 1,
        #[Assert\All([new Assert\Ulid()])] public array $modifierIds = [],
        #[Assert\Length(max: 255)] public ?string $notes = null,
    ) {
    }
}
