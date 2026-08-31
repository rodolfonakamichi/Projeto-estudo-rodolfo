<?php

declare(strict_types=1);

namespace Tests\Domain\Identity;

use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use App\Domain\Shared\Ulid;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase
{
    public function test_gera_e_reconstroi(): void
    {
        $id = CompanyId::generate();

        self::assertTrue($id->equals(CompanyId::fromString($id->toString())));
    }

    public function test_tipos_diferentes_nunca_sao_iguais(): void
    {
        $ulid = Ulid::generate()->toString();

        self::assertFalse(CompanyId::fromString($ulid)->equals(UserId::fromString($ulid)));
    }

    public function test_ulid_invalido_lanca_erro(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CompanyId::fromString('nao-e-ulid');
    }

    public function test_stringable(): void
    {
        $id = CompanyId::generate();

        self::assertSame($id->toString(), (string) $id);
        self::assertSame(26, \strlen((string) $id));
    }
}
