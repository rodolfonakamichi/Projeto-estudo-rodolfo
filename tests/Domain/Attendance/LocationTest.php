<?php

declare(strict_types=1);

namespace App\Tests\Domain\Attendance;

use App\Domain\Attendance\Location;
use App\Domain\Attendance\LocationId;
use App\Domain\Attendance\LocationType;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Capability;
use App\Domain\Identity\CompanyId;
use App\Domain\Shared\DomainException;
use PHPUnit\Framework\TestCase;

final class LocationTest extends TestCase
{
    public function test_nasce_ativo_com_rotulo_aparado(): void
    {
        $table = Location::create(LocationId::generate(), CompanyId::generate(), BranchId::generate(), LocationType::Table, '  Mesa 12 ');

        self::assertSame('Mesa 12', $table->label());
        self::assertTrue($table->isActive());
    }

    public function test_rotulo_vazio_e_recusado(): void
    {
        $this->expectException(DomainException::class);
        Location::create(LocationId::generate(), CompanyId::generate(), BranchId::generate(), LocationType::Counter, ' ');
    }

    public function test_capability_exigida_por_tipo(): void
    {
        self::assertSame(Capability::Tables, LocationType::Table->requiredCapability());
        self::assertSame(Capability::Hotel, LocationType::Room->requiredCapability());
        self::assertNull(LocationType::Counter->requiredCapability());
    }
}
