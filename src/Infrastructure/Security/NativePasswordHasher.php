<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Identity\PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher as SymfonyNativeHasher;

/**
 * Adapter da porta PasswordHasher: bcrypt/argon2 (o melhor que o PHP tiver),
 * via componente PasswordHasher do Symfony. Serve para senha e para PIN.
 */
final readonly class NativePasswordHasher implements PasswordHasher
{
    private SymfonyNativeHasher $hasher;

    public function __construct()
    {
        $this->hasher = new SymfonyNativeHasher();
    }

    public function hash(string $plain): string
    {
        return $this->hasher->hash($plain);
    }

    public function verify(string $hash, string $plain): bool
    {
        return $this->hasher->verify($hash, $plain);
    }
}
