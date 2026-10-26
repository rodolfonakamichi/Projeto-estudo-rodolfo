<?php

declare(strict_types=1);

namespace App\Tests\Domain\Identity;

use App\Domain\Identity\Email;
use App\Domain\Identity\RoleCode;
use PHPUnit\Framework\TestCase;

final class EmailAndRoleCodeTest extends TestCase
{
    public function test_email_e_normalizado(): void
    {
        self::assertSame('ana@bar.com', Email::fromString('  Ana@Bar.COM ')->toString());
    }

    public function test_email_invalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Email::fromString('ana@');
    }

    public function test_role_code_invalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RoleCode::fromString('garçom');
    }
}
