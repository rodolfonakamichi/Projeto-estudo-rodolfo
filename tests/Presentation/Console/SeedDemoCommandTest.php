<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Console;

use App\Application\Identity\Authorization\CapabilityChecker;
use App\Domain\Identity\Capability;
use App\Domain\Identity\CompanyRepository;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Email;
use App\Domain\Identity\Permission;
use App\Domain\Identity\Slug;
use App\Domain\Identity\UserRepository;
use App\Presentation\Console\Demo\SeedDemoCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/** Aceite M1.6 (doc 07): empresa demo, 1 filial, 1 usuário por papel; `hotel` = false. */
final class SeedDemoCommandTest extends KernelTestCase
{
    public function test_cria_a_empresa_demo_com_um_usuario_por_papel(): void
    {
        $tester = $this->runSeed();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('garcom@demo.com', $tester->getDisplay());

        $company = self::getContainer()->get(CompanyRepository::class)->getBySlug(Slug::fromString(SeedDemoCommand::SLUG));
        self::assertCount(1, $company->branches());

        $checker = self::getContainer()->get(CapabilityChecker::class);
        self::assertTrue($checker->has($company->id(), Capability::Kitchen));
        self::assertFalse($checker->has($company->id(), Capability::Hotel), 'aceite M1: hotel = false na demo');

        $users = self::getContainer()->get(UserRepository::class);
        $branchId = $company->branches()[0]->id();
        $assignedRoles = [];
        foreach (['dono', 'admin', 'auditor', 'gerente', 'caixa', 'garcom', 'cozinha', 'bar', 'estoque'] as $localPart) {
            $user = $users->findByEmail($company->id(), Email::fromString("{$localPart}@demo.com"));
            self::assertNotNull($user, $localPart);
            $assignedRoles[] = $user->roleAssignments()[0]->role()->code()->toString();
        }
        sort($assignedRoles);
        $allRoles = array_keys(DefaultRoles::matrix());
        sort($allRoles);
        self::assertSame($allRoles, $assignedRoles, 'um usuário para cada papel padrão');

        $waiter = $users->findByEmail($company->id(), Email::fromString('garcom@demo.com'));
        self::assertNotNull($waiter);
        self::assertFalse($waiter->can(Permission::CommandClose, $branchId));
        self::assertNotNull($waiter->pinHash());
    }

    public function test_rodar_de_novo_nao_duplica(): void
    {
        $this->runSeed();
        $second = $this->runSeed();

        self::assertSame(Command::SUCCESS, $second->getStatusCode());
        self::assertStringContainsString('já existe', $second->getDisplay());
    }

    private function runSeed(): CommandTester
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('app:seed-demo'));
        $tester->execute([]);

        return $tester;
    }
}
