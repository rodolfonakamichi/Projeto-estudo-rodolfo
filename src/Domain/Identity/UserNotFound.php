<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\DomainException;

final class UserNotFound extends DomainException
{
    public static function withId(UserId $id): self
    {
        return new self("Usuário {$id} não encontrado.");
    }
}
