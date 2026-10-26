<?php

declare(strict_types=1);

namespace App\Presentation\Http\Attendance;

use Symfony\Component\Validator\Constraints as Assert;

/** Motivo obrigatório (cancelar item ou comanda vai para a auditoria). */
final readonly class ReasonRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 255)] public string $reason = '',
    ) {
    }
}
