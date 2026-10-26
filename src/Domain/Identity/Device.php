<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\DomainException;

/**
 * Aparelho pareado (tablet do salão, KDS). Raiz de agregado.
 *
 * Um gerente pareia o aparelho a UMA filial; o aparelho recebe um token `d_`
 * que só serve para listar os usuários daquela filial e trocar PIN por token
 * de usuário. Não expira — some quando revogado (tablet perdido/roubado).
 */
class Device
{
    private ?\DateTimeImmutable $lastUsedAt = null;
    private ?\DateTimeImmutable $revokedAt = null;
    private readonly \DateTimeImmutable $createdAt;
    private readonly string $tokenHash;

    private function __construct(
        private DeviceId $id, // sem readonly: proxy do Doctrine regrava o id (PHP 8.3)
        private readonly Company $company,
        private readonly Branch $branch,
        private readonly User $pairedBy,
        private string $name,
        OpaqueToken $token,
        \DateTimeImmutable $now,
    ) {
        if (TokenKind::Device !== $token->kind()) {
            throw new \InvalidArgumentException('Device exige token do tipo aparelho (d_).');
        }
        $this->tokenHash = $token->hash();
        $this->createdAt = $now;
    }

    public static function pair(DeviceId $id, Branch $branch, User $pairedBy, OpaqueToken $token, string $name, \DateTimeImmutable $now): self
    {
        if (!$pairedBy->companyId()->equals($branch->companyId())) {
            throw new DomainException('A filial pertence a outra empresa.');
        }
        if (!$branch->isActive()) {
            throw new DomainException('Não é possível parear aparelho em filial inativa.');
        }

        $name = trim($name);
        if ('' === $name || mb_strlen($name) > 80) {
            throw new DomainException('Nome do aparelho deve ter entre 1 e 80 caracteres.');
        }

        return new self($id, $pairedBy->company(), $branch, $pairedBy, $name, $token, $now);
    }

    public function isActive(): bool
    {
        return null === $this->revokedAt && $this->branch->isActive();
    }

    public function use(\DateTimeImmutable $now): void
    {
        if (!$this->isActive()) {
            throw new InvalidCredentials();
        }
        $this->lastUsedAt = $now;
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }

    public function id(): DeviceId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function companyId(): CompanyId
    {
        return $this->company->id();
    }

    public function branchId(): BranchId
    {
        return $this->branch->id();
    }
}
