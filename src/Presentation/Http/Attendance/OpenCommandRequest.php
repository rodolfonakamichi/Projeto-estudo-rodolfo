<?php

declare(strict_types=1);

namespace App\Presentation\Http\Attendance;

use Symfony\Component\Validator\Constraints as Assert;

/** `{"bindType": "MESA", "bindRef": "<id da mesa>"}` ou `{"bindType": "BALCAO", "bindRef": "João"}`. */
final readonly class OpenCommandRequest
{
    public function __construct(
        #[Assert\NotBlank] public string $bindType = '',
        #[Assert\Length(max: 60)] public ?string $bindRef = null,
    ) {
    }
}
