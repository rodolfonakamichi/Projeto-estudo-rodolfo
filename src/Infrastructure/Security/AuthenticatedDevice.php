<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/** Aparelho pareado autenticado (token `d_`). Só tem ROLE_DEVICE. */
final readonly class AuthenticatedDevice implements UserInterface
{
    public function __construct(
        public string $deviceId,
        public string $companyId,
        public string $branchId,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return 'device:'.$this->deviceId;
    }

    public function getRoles(): array
    {
        return ['ROLE_DEVICE'];
    }

    public function eraseCredentials(): void
    {
    }
}
