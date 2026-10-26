<?php

declare(strict_types=1);

namespace App\Domain\Identity;

interface ApiTokenRepository
{
    public function save(ApiToken $token): void;

    public function findByToken(OpaqueToken $token): ?ApiToken;
}
