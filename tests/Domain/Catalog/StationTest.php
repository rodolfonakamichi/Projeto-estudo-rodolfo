<?php

declare(strict_types=1);

namespace App\Tests\Domain\Catalog;

use App\Domain\Catalog\Station;
use App\Domain\Catalog\StationId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Shared\DomainException;
use PHPUnit\Framework\TestCase;

final class StationTest extends TestCase
{
    public function test_nasce_ativa_e_pode_ser_desativada(): void
    {
        $station = Station::create(StationId::generate(), CompanyId::generate(), BranchId::generate(), 'Cozinha');

        self::assertTrue($station->isActive());
        $station->deactivate();
        self::assertFalse($station->isActive());
    }

    public function test_nome_acima_de_60_caracteres_e_recusado(): void
    {
        $this->expectException(DomainException::class);
        Station::create(StationId::generate(), CompanyId::generate(), BranchId::generate(), str_repeat('x', 61));
    }
}
