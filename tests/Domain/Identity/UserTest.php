<?php

declare(strict_types=1);

namespace App\Tests\Domain\Identity;

use App\Domain\Identity\Branch;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Company;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Email;
use App\Domain\Identity\Permission;
use App\Domain\Identity\Role;
use App\Domain\Identity\RoleCode;
use App\Domain\Identity\RoleId;
use App\Domain\Identity\Slug;
use App\Domain\Identity\User;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\Percentage;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    private Company $company;
    private Branch $matriz;
    private Branch $centro;
    /** @var array<string, Role> */
    private array $roles;

    protected function setUp(): void
    {
        $this->company = Company::register(CompanyId::generate(), 'Bar do Zé', Slug::fromString('bar-do-ze'), BranchId::generate(), 'Matriz');
        $this->matriz = $this->company->branches()[0];
        $this->centro = $this->company->addBranch(BranchId::generate(), 'Centro', 'America/Sao_Paulo');
        foreach (DefaultRoles::for($this->company) as $role) {
            $this->roles[$role->code()->toString()] = $role;
        }
    }

    public function test_aceite_m1_waiter_nao_fecha_comanda_e_cashier_fecha(): void
    {
        $waiter = $this->user();
        $waiter->assignRole($this->roles['WAITER']);
        $cashier = $this->user();
        $cashier->assignRole($this->roles['CASHIER']);

        self::assertFalse($waiter->can(Permission::CommandClose, $this->matriz->id()));
        self::assertTrue($cashier->can(Permission::CommandClose, $this->matriz->id()));
    }

    public function test_papel_de_filial_so_vale_naquela_filial(): void
    {
        $user = $this->user();
        $user->assignRole($this->roles['WAITER'], $this->centro);

        self::assertTrue($user->can(Permission::CommandOpen, $this->centro->id()));
        self::assertFalse($user->can(Permission::CommandOpen, $this->matriz->id()));
        self::assertFalse($user->can(Permission::CommandOpen), 'ação no nível da empresa exige papel da empresa toda');
    }

    public function test_papel_da_empresa_toda_vale_em_qualquer_filial(): void
    {
        $user = $this->user();
        $user->assignRole($this->roles['CASHIER']);

        self::assertTrue($user->can(Permission::PaymentCreate, $this->matriz->id()));
        self::assertTrue($user->can(Permission::PaymentCreate, $this->centro->id()));
    }

    public function test_papeis_sao_cumulativos(): void
    {
        $user = $this->user();
        $user->assignRole($this->roles['WAITER']);
        $user->assignRole($this->roles['STOCK']);

        self::assertTrue($user->can(Permission::CommandOpen));
        self::assertTrue($user->can(Permission::StockAdjust));
        self::assertFalse($user->can(Permission::CashClose));
    }

    public function test_usuario_inativo_nao_pode_nada(): void
    {
        $user = $this->user();
        $user->assignRole($this->roles['OWNER']);
        $user->deactivate();

        self::assertFalse($user->can(Permission::CommandOpen));
    }

    public function test_nao_atribui_o_mesmo_papel_duas_vezes_no_mesmo_escopo(): void
    {
        $user = $this->user();
        $user->assignRole($this->roles['WAITER'], $this->centro);
        $user->assignRole($this->roles['WAITER']); // escopo diferente: ok

        $this->expectException(DomainException::class);
        $user->assignRole($this->roles['WAITER'], $this->centro);
    }

    public function test_nao_aceita_papel_ou_filial_de_outra_empresa(): void
    {
        $outra = Company::register(CompanyId::generate(), 'Outra', Slug::fromString('outra'), BranchId::generate(), 'Matriz');
        $papelDeFora = Role::create(RoleId::generate(), $outra, RoleCode::fromString('WAITER'), 'Garçom');
        $user = $this->user();

        try {
            $user->assignRole($papelDeFora);
            self::fail('aceitou papel de outra empresa');
        } catch (DomainException) {
        }

        $this->expectException(DomainException::class);
        $user->assignRole($this->roles['WAITER'], $outra->branches()[0]);
    }

    public function test_revoga_papel(): void
    {
        $user = $this->user();
        $user->assignRole($this->roles['CASHIER']);
        $user->revokeRole($this->roles['CASHIER']);

        self::assertFalse($user->can(Permission::PaymentCreate));
        self::assertSame([], $user->roleAssignments());
    }

    public function test_limite_de_desconto_e_o_maior_entre_os_papeis(): void
    {
        $user = $this->user();
        self::assertSame(0, $user->maxDiscountIn($this->matriz->id())?->hundredths(), 'sem papel = 0%');

        $user->assignRole($this->roles['WAITER']);
        $user->assignRole($this->roles['CASHIER'], $this->centro);

        self::assertTrue(Percentage::fromHundredths(500)->equals($user->maxDiscountIn($this->matriz->id()) ?? Percentage::fromHundredths(0)));
        self::assertTrue(Percentage::fromHundredths(1000)->equals($user->maxDiscountIn($this->centro->id()) ?? Percentage::fromHundredths(0)));

        $user->assignRole($this->roles['OWNER']);
        self::assertNull($user->maxDiscountIn($this->matriz->id()), 'OWNER = sem limite');
    }

    private function user(): User
    {
        return User::register(UserId::generate(), $this->company, 'Ana', Email::fromString('ana@bar.com'), 'hash');
    }
}
