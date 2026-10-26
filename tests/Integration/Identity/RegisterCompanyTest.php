<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Capability;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\CompanyRepository;
use App\Domain\Shared\DomainException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Caminho completo: CommandBus -> handler -> repositório Doctrine -> MySQL
 * (banco comandas_test). Cada teste roda numa transação desfeita no fim
 * (dama/doctrine-test-bundle).
 */
final class RegisterCompanyTest extends KernelTestCase
{
    public function test_cadastra_e_recarrega_do_banco(): void
    {
        $companyId = CompanyId::generate();
        $this->bus()->dispatch($this->command($companyId, 'bar-do-ze', [Capability::Tables, Capability::Kitchen]));

        // esquece o que está em memória: o próximo get() vem do banco
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $company = self::getContainer()->get(CompanyRepository::class)->get($companyId);

        self::assertSame('Bar do Zé', $company->name());
        self::assertSame('bar-do-ze', $company->slug()->toString());
        self::assertSame('Matriz', $company->branches()[0]->name());
        self::assertTrue($company->hasCapability(Capability::Kitchen));
        self::assertFalse($company->hasCapability(Capability::Hotel));
    }

    public function test_rejeita_slug_ja_usado(): void
    {
        $this->bus()->dispatch($this->command(CompanyId::generate(), 'bar-do-ze'));

        $this->expectException(DomainException::class);
        $this->bus()->dispatch($this->command(CompanyId::generate(), 'bar-do-ze'));
    }

    private function bus(): CommandBus
    {
        return self::getContainer()->get(CommandBus::class);
    }

    /** @param list<Capability> $capabilities */
    private function command(CompanyId $id, string $slug, array $capabilities = []): RegisterCompany
    {
        return new RegisterCompany(
            companyId: $id->toString(),
            name: 'Bar do Zé',
            slug: $slug,
            branchId: BranchId::generate()->toString(),
            branchName: 'Matriz',
            capabilities: $capabilities,
        );
    }
}
