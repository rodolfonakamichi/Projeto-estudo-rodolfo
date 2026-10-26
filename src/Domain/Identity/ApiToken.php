<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Token de acesso de um usuário (linha de `api_tokens`). Raiz de agregado.
 *
 * Expiração deslizante: cada uso empurra `expiresAt` para agora + ociosidade
 * (12h por padrão) — o turno não cai enquanto a pessoa trabalha; esqueceu o
 * tablet aberto, expira sozinho. `idle = null` = não expira (integrações).
 */
class ApiToken
{
    private ?\DateTimeImmutable $lastUsedAt = null;
    private ?\DateTimeImmutable $expiresAt;
    private ?\DateTimeImmutable $revokedAt = null;
    private readonly \DateTimeImmutable $createdAt;
    private readonly string $tokenHash;

    private function __construct(
        private ApiTokenId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly Company $company,
        private readonly User $user,
        private readonly string $name,
        OpaqueToken $token,
        \DateTimeImmutable $now,
        ?\DateInterval $idle,
    ) {
        if (TokenKind::User !== $token->kind()) {
            throw new \InvalidArgumentException('ApiToken exige token do tipo usuário (u_).');
        }
        $this->tokenHash = $token->hash();
        $this->createdAt = $now;
        $this->expiresAt = null === $idle ? null : $now->add($idle);
    }

    public static function issue(ApiTokenId $id, User $user, OpaqueToken $token, string $name, \DateTimeImmutable $now, ?\DateInterval $idle): self
    {
        if (!$user->isActive()) {
            throw new InvalidCredentials();
        }

        return new self($id, $user->company(), $user, mb_substr(trim($name), 0, 80), $token, $now, $idle);
    }

    public function isValidAt(\DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt
            && (null === $this->expiresAt || $now < $this->expiresAt)
            && $this->user->isActive();
    }

    /** Registra o uso e renova a expiração. Token inválido = credencial inválida. */
    public function use(\DateTimeImmutable $now, ?\DateInterval $idle): void
    {
        if (!$this->isValidAt($now)) {
            throw new InvalidCredentials();
        }

        $this->lastUsedAt = $now;
        if (null !== $this->expiresAt && null !== $idle) {
            $this->expiresAt = $now->add($idle);
        }
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }

    public function id(): ApiTokenId
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function companyId(): CompanyId
    {
        return $this->company->id();
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
