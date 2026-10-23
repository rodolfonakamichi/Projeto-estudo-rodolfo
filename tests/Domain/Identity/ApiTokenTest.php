<?php

declare(strict_types=1);

namespace App\Tests\Domain\Identity;

use App\Domain\Identity\ApiToken;
use App\Domain\Identity\ApiTokenId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Company;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\Email;
use App\Domain\Identity\InvalidCredentials;
use App\Domain\Identity\OpaqueToken;
use App\Domain\Identity\Slug;
use App\Domain\Identity\TokenKind;
use App\Domain\Identity\User;
use App\Domain\Identity\UserId;
use PHPUnit\Framework\TestCase;

final class ApiTokenTest extends TestCase
{
    private User $user;
    private \DateInterval $idle;
    private \DateTimeImmutable $t0;

    protected function setUp(): void
    {
        $company = Company::register(CompanyId::generate(), 'Bar', Slug::fromString('bar'), BranchId::generate(), 'Matriz');
        $this->user = User::register(UserId::generate(), $company, 'Ana', Email::fromString('ana@bar.com'), 'hash');
        $this->idle = new \DateInterval('PT12H');
        $this->t0 = new \DateTimeImmutable('2026-10-23 08:00:00');
    }

    public function test_expira_depois_de_12h_sem_uso(): void
    {
        $token = $this->issue();

        self::assertTrue($token->isValidAt($this->t0->modify('+11 hours')));
        self::assertFalse($token->isValidAt($this->t0->modify('+12 hours')));
    }

    public function test_cada_uso_renova_a_expiracao(): void
    {
        $token = $this->issue();

        $token->use($this->t0->modify('+10 hours'), $this->idle);

        self::assertTrue($token->isValidAt($this->t0->modify('+21 hours')), 'turno longo, mas em uso');
        self::assertFalse($token->isValidAt($this->t0->modify('+22 hours')));
    }

    public function test_token_revogado_nao_pode_ser_usado(): void
    {
        $token = $this->issue();
        $token->revoke($this->t0);

        $this->expectException(InvalidCredentials::class);
        $token->use($this->t0, $this->idle);
    }

    public function test_usuario_desativado_derruba_o_token(): void
    {
        $token = $this->issue();
        $this->user->deactivate();

        self::assertFalse($token->isValidAt($this->t0));
    }

    public function test_token_sem_ociosidade_nao_expira(): void
    {
        $token = ApiToken::issue(ApiTokenId::generate(), $this->user, OpaqueToken::generate(TokenKind::User), 'integração', $this->t0, null);

        self::assertNull($token->expiresAt());
        self::assertTrue($token->isValidAt($this->t0->modify('+1 year')));
    }

    public function test_opaque_token_guarda_hash_e_reconhece_o_tipo(): void
    {
        $token = OpaqueToken::generate(TokenKind::Device);
        $again = OpaqueToken::fromPlain($token->plain());

        self::assertStringStartsWith('d_', $token->plain());
        self::assertSame(TokenKind::Device, $again->kind());
        self::assertSame(hash('sha256', $token->plain()), $again->hash());
        self::assertSame(64, \strlen($token->hash()));
    }

    public function test_opaque_token_rejeita_formato_desconhecido(): void
    {
        $this->expectException(InvalidCredentials::class);
        OpaqueToken::fromPlain('Bearer abc');
    }

    private function issue(): ApiToken
    {
        return ApiToken::issue(ApiTokenId::generate(), $this->user, OpaqueToken::generate(TokenKind::User), 'tablet', $this->t0, $this->idle);
    }
}
