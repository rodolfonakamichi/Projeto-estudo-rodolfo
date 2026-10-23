<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Token opaco de acesso: `u_<64 hex>` (usuário) ou `d_<64 hex>` (aparelho).
 * O texto em claro só existe na emissão (vai para o cliente); o banco guarda
 * o SHA-256. Vazou o banco? Os hashes não servem para autenticar.
 *
 * SHA-256 simples (sem bcrypt) é seguro aqui porque o segredo tem 256 bits
 * aleatórios — diferente de senha, não há o que chutar.
 */
final readonly class OpaqueToken
{
    private const PATTERN = '/^([ud])_([0-9a-f]{64})$/';

    private function __construct(
        private TokenKind $kind,
        #[\SensitiveParameter] private string $plain,
    ) {
    }

    public static function generate(TokenKind $kind): self
    {
        return new self($kind, $kind->value.'_'.bin2hex(random_bytes(32)));
    }

    public static function fromPlain(#[\SensitiveParameter] string $plain): self
    {
        if (1 !== preg_match(self::PATTERN, $plain, $m)) {
            throw new InvalidCredentials();
        }

        return new self(TokenKind::from($m[1]), $plain);
    }

    public function kind(): TokenKind
    {
        return $this->kind;
    }

    public function plain(): string
    {
        return $this->plain;
    }

    public function hash(): string
    {
        return hash('sha256', $this->plain);
    }
}
