<?php

declare(strict_types=1);

namespace App\Application\Identity\Auth;

/**
 * Política de expiração do token de usuário (ISO 8601, ex. PT12H; valor em
 * config/services.yaml, parâmetro app.auth.user_token_idle).
 * Um objeto em vez de string solta: quem precisa da regra injeta a política.
 */
final readonly class TokenPolicy
{
    public \DateInterval $userTokenIdle;

    public function __construct(string $userTokenIdle)
    {
        $this->userTokenIdle = new \DateInterval($userTokenIdle);
    }
}
