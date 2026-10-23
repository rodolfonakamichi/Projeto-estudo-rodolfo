<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/** Gravado como VARCHAR (ADR-007: nada de ENUM no banco). */
enum CompanyStatus: string
{
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
}
