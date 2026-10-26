<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\Permission;
use App\Domain\Identity\RoleCode;
use App\Domain\Identity\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Regressão (achado no M2.5): revogar permissão quebrava no flush — o Doctrine
 * passa a parte "associação" da PK composta (role_id) como string para o tipo
 * de ID, que só aceitava o objeto.
 */
final class RolePermissionPersistenceTest extends KernelTestCase
{
    public function test_revogar_permissao_grava_e_recarrega(): void
    {
        $companyId = CompanyId::generate();
        self::getContainer()->get(CommandBus::class)->dispatch(new RegisterCompany($companyId->toString(), 'Bar do Zé', 'bar-do-ze', BranchId::generate()->toString(), 'Matriz'));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $roles = self::getContainer()->get(RoleRepository::class);
        $entityManager->clear();

        $waiter = $roles->getByCode($companyId, RoleCode::fromString('WAITER'));
        self::assertTrue($waiter->grants(Permission::CommandOpen));
        $waiter->revoke(Permission::CommandOpen);
        $entityManager->flush();
        $entityManager->clear();

        self::assertFalse($roles->getByCode($companyId, RoleCode::fromString('WAITER'))->grants(Permission::CommandOpen));
    }
}
