<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\NotFound;

final class RoleNotFound extends NotFound
{
    public static function withCode(RoleCode $code): self
    {
        return new self("Papel {$code->toString()} não existe nesta empresa.");
    }
}
