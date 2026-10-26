<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\NotFound;

final class DeviceNotFound extends NotFound
{
    public static function withId(DeviceId $id): self
    {
        return new self("Aparelho {$id} não encontrado.");
    }
}
