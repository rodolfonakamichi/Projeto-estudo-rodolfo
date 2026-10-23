<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * O "usuário" que o Symfony Security enxerga. Adapter: NÃO é a entidade
 * Domain\Identity\User (que não deve conhecer o framework) — só carrega os IDs.
 */
final readonly class AuthenticatedUser implements UserInterface
{
    public function __construct(
        public string $userId,
        public string $companyId,
        #[\SensitiveParameter] public string $plainToken,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return 'user:'.$this->userId;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
