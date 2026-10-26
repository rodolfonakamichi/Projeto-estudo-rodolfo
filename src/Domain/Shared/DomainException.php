<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Violação de regra de negócio (invariante). A mensagem é voltada ao usuário,
 * em português. A Presentation traduz para HTTP 422.
 */
class DomainException extends \DomainException
{
}
