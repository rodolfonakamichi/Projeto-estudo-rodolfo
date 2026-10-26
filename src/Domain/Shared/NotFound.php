<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Recurso inexistente — ou de outra empresa, escondido pelo CompanyFilter (para
 * quem pergunta, é a mesma coisa). A Presentation traduz para HTTP 404.
 */
class NotFound extends DomainException
{
}
