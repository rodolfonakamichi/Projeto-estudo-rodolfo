<?php

declare(strict_types=1);

namespace App\Tests\Domain\Identity;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\Company;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Permission;
use App\Domain\Identity\Role;
use App\Domain\Identity\RoleCode;
use App\Domain\Identity\RoleId;
use App\Domain\Identity\Slug;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function test_concede_e_revoga_sem_duplicar(): void
    {
        $role = Role::create(RoleId::generate(), $this->company(), RoleCode::fromString('host'), 'Recepção', [Permission::CommandOpen]);

        $role->grant(Permission::CommandOpen);
        $role->grant(Permission::CommandBillRequest);
        $role->revoke(Permission::CommandOpen);

        self::assertSame('HOST', $role->code()->toString(), 'código é normalizado para maiúsculas');
        self::assertSame([Permission::CommandBillRequest], $role->permissions());
    }

    public function test_papeis_padrao_seguem_a_matriz_do_doc_06(): void
    {
        $roles = [];
        foreach (DefaultRoles::for($this->company()) as $role) {
            $roles[$role->code()->toString()] = $role;
        }

        self::assertSame(['OWNER', 'ADMIN', 'MANAGER', 'CASHIER', 'WAITER', 'KITCHEN', 'BAR', 'STOCK', 'AUDITOR'], array_keys($roles));
        self::assertCount(\count(Permission::cases()) - 1, $roles['OWNER']->permissions(), 'OWNER tem tudo menos command.transfer (Fase 2)');
        self::assertFalse($roles['ADMIN']->grants(Permission::CashCloseViewExpected), 'fechamento às cegas');
        self::assertTrue($roles['KITCHEN']->grants(Permission::ProductionReady));
        self::assertFalse($roles['KITCHEN']->grants(Permission::PaymentCreate));
        self::assertSame('5.00', $roles['WAITER']->maxDiscount()?->toString());
        self::assertNull($roles['OWNER']->maxDiscount());
    }

    private function company(): Company
    {
        return Company::register(CompanyId::generate(), 'Bar', Slug::fromString('bar'), BranchId::generate(), 'Matriz');
    }
}
