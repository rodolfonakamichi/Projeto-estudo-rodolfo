<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/** Porta: gera/confere hash de senha e PIN. Adapter: Symfony PasswordHasher. */
interface PasswordHasher
{
    public function hash(string $plain): string;

    public function verify(string $hash, string $plain): bool;
}
