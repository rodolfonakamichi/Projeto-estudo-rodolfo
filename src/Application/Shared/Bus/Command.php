<?php

declare(strict_types=1);

namespace App\Application\Shared\Bus;

/**
 * Intenção de mudar o estado do sistema (`OpenCommand`, `AddItemToCommand`).
 * DTO imutável, só dados — a regra fica no handler e no Domain.
 */
interface Command
{
}
