<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Application\Identity\Authorization\CapabilityChecker;
use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Capability;
use App\Domain\Identity\CompanyId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Aceite M1.5 (doc 07): CapabilityChecker retorna false para `hotel` num bar. */
final class CapabilityCheckerTest extends KernelTestCase
{
    public function test_bar_com_mesas_e_cozinha_nao_e_hotel(): void
    {
        $companyId = CompanyId::generate();
        self::getContainer()->get(CommandBus::class)->dispatch(new RegisterCompany(
            companyId: $companyId->toString(),
            name: 'Bar do Zé',
            slug: 'bar-do-ze',
            branchId: BranchId::generate()->toString(),
            branchName: 'Matriz',
            capabilities: [Capability::Tables, Capability::Kitchen],
        ));

        $checker = self::getContainer()->get(CapabilityChecker::class);

        self::assertTrue($checker->has($companyId, Capability::Tables));
        self::assertTrue($checker->has($companyId, Capability::Kitchen));
        self::assertFalse($checker->has($companyId, Capability::Hotel));
        self::assertFalse($checker->has($companyId, Capability::Inventory));
    }
}
