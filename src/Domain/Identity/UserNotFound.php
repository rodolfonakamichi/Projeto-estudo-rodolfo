<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\NotFound;

final class UserNotFound extends NotFound
{
    public static function withId(UserId $id): self
    {
        return new self("Usuário {$id} não encontrado.");
    }
}
