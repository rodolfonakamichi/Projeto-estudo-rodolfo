<?php

declare(strict_types=1);

namespace App\Application\Identity\RegisterUser;

use App\Application\Shared\Bus\Command;

final readonly class RegisterUser implements Command
{
    /**
     * @param list<RoleAssignment> $roles
     */
    public function __construct(
        public string $userId,
        public string $companyId,
        public string $name,
        public string $email,
        #[\SensitiveParameter] public string $password,
        public array $roles = [],
        #[\SensitiveParameter] public ?string $pin = null,
    ) {
    }
}
