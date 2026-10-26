<?php

declare(strict_types=1);

namespace App\Tests\Domain\Identity;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\BranchStatus;
use App\Domain\Identity\Capability;
use App\Domain\Identity\Company;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\CompanyStatus;
use App\Domain\Identity\Slug;
use App\Domain\Shared\DomainException;
use PHPUnit\Framework\TestCase;

final class CompanyTest extends TestCase
{
    public function test_nasce_ativa_e_com_a_primeira_filial(): void
    {
        $company = $this->company();

        self::assertSame(CompanyStatus::Active, $company->status());
        self::assertCount(1, $company->branches());
        self::assertSame('Matriz', $company->branches()[0]->name());
        self::assertTrue($company->id()->equals($company->branches()[0]->companyId()));
    }

    public function test_nao_aceita_duas_filiais_com_o_mesmo_nome(): void
    {
        $company = $this->company();

        $this->expectException(DomainException::class);
        $company->addBranch(BranchId::generate(), '  matriz ', 'America/Sao_Paulo');
    }

    public function test_nao_desativa_a_ultima_filial_ativa(): void
    {
        $company = $this->company();

        $this->expectException(DomainException::class);
        $company->deactivateBranch($company->branches()[0]->id());
    }

    public function test_desativa_filial_quando_sobra_outra_ativa(): void
    {
        $company = $this->company();
        $centro = $company->addBranch(BranchId::generate(), 'Centro', 'America/Sao_Paulo');

        $company->deactivateBranch($centro->id());

        self::assertSame(BranchStatus::Inactive, $centro->status());
        self::assertCount(1, $company->activeBranches());
    }

    public function test_rejeita_fuso_horario_invalido(): void
    {
        $this->expectException(DomainException::class);
        $this->company()->addBranch(BranchId::generate(), 'Centro', 'America/Atlantida');
    }

    public function test_liga_e_desliga_capabilities(): void
    {
        $company = $this->company();

        $company->enableCapability(Capability::Kitchen);
        $company->enableCapability(Capability::Kitchen); // idempotente
        $company->enableCapability(Capability::Tables);
        $company->disableCapability(Capability::Tables);

        self::assertTrue($company->hasCapability(Capability::Kitchen));
        self::assertFalse($company->hasCapability(Capability::Tables));
        self::assertFalse($company->hasCapability(Capability::Hotel));
        self::assertSame([Capability::Kitchen], $company->enabledCapabilities());
    }

    public function test_touch_preserva_created_at_e_avanca_updated_at(): void
    {
        $company = $this->company();
        $t1 = new \DateTimeImmutable('2026-01-01 10:00:00');
        $t2 = new \DateTimeImmutable('2026-01-02 10:00:00');

        $company->touch($t1);
        $company->touch($t2);

        $read = \Closure::bind(fn (string $property): mixed => $this->{$property}, $company, Company::class);
        self::assertEquals($t1, $read('createdAt'));
        self::assertEquals($t2, $read('updatedAt'));
    }

    private function company(): Company
    {
        return Company::register(CompanyId::generate(), 'Bar do Zé', Slug::fromString('bar-do-ze'), BranchId::generate(), 'Matriz');
    }
}
