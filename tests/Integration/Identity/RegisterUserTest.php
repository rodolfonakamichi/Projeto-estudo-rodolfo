<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Identity\RegisterUser\RegisterUser;
use App\Application\Identity\RegisterUser\RoleAssignment;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\PasswordHasher;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Identity\UserRepository;
use App\Domain\Shared\DomainException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RegisterUserTest extends KernelTestCase
{
    private CompanyId $companyId;
    private BranchId $branchId;

    protected function setUp(): void
    {
        $this->companyId = CompanyId::generate();
        $this->branchId = BranchId::generate();
        $this->bus()->dispatch(new RegisterCompany(
            companyId: $this->companyId->toString(),
            name: 'Bar do Zé',
            slug: 'bar-do-ze',
            branchId: $this->branchId->toString(),
            branchName: 'Matriz',
        ));
    }

    public function test_cadastra_com_papeis_e_recarrega_do_banco(): void
    {
        $userId = UserId::generate();
        $this->bus()->dispatch($this->command($userId, 'ana@bar.com', [
            new RoleAssignment('CASHIER'),
            new RoleAssignment('WAITER', $this->branchId->toString()),
        ], pin: '1234'));

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $user = self::getContainer()->get(UserRepository::class)->get($userId);
        $hasher = self::getContainer()->get(PasswordHasher::class);

        self::assertTrue($hasher->verify($user->passwordHash(), 'segredo123'));
        self::assertTrue($hasher->verify((string) $user->pinHash(), '1234'));
        self::assertTrue($user->can(Permission::CommandClose, $this->branchId));
        self::assertTrue($user->can(Permission::CommandOpen, $this->branchId));
        self::assertCount(2, $user->roleAssignments());
    }

    public function test_email_e_unico_na_empresa_mesmo_com_maiusculas(): void
    {
        $this->bus()->dispatch($this->command(UserId::generate(), 'ana@bar.com'));

        $this->expectException(DomainException::class);
        $this->bus()->dispatch($this->command(UserId::generate(), 'ANA@bar.com'));
    }

    public function test_papel_inexistente_falha_e_nada_e_gravado(): void
    {
        $userId = UserId::generate();

        try {
            $this->bus()->dispatch($this->command($userId, 'ze@bar.com', [new RoleAssignment('GARCOM')]));
            self::fail('aceitou papel inexistente');
        } catch (DomainException) {
        }

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->expectException(DomainException::class);
        self::getContainer()->get(UserRepository::class)->get($userId);
    }

    private function bus(): CommandBus
    {
        return self::getContainer()->get(CommandBus::class);
    }

    /** @param list<RoleAssignment> $roles */
    private function command(UserId $id, string $email, array $roles = [], ?string $pin = null): RegisterUser
    {
        return new RegisterUser($id->toString(), $this->companyId->toString(), 'Ana', $email, 'segredo123', $roles, $pin);
    }
}
