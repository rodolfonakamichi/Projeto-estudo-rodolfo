<?php

declare(strict_types=1);

namespace App\Tests\Integration\MultiCompany;

use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Identity\RegisterUser\RegisterUser;
use App\Application\Identity\RegisterUser\RoleAssignment;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyContext;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\CompanyNotFound;
use App\Domain\Identity\CompanyRepository;
use App\Domain\Identity\Role;
use App\Domain\Identity\Slug;
use App\Domain\Identity\User;
use App\Domain\Identity\UserId;
use App\Domain\Identity\UserNotFound;
use App\Domain\Identity\UserRepository;
use App\Infrastructure\Security\CompanyContextHolder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Aceite M1.4 (doc 07): usuário da empresa A NÃO enxerga dado da empresa B,
 * mesmo forçando o `id` na consulta.
 */
final class CompanyIsolationTest extends KernelTestCase
{
    private CompanyId $companyA;
    private CompanyId $companyB;
    private UserId $userA;
    private UserId $userB;

    protected function setUp(): void
    {
        [$this->companyA, $this->userA] = $this->registerCompanyWithUser('Bar do Zé', 'bar-do-ze');
        [$this->companyB, $this->userB] = $this->registerCompanyWithUser('Cantina da Lu', 'cantina-da-lu');

        // esvazia o identity map: sem isso o find() devolve o objeto da memória sem ir ao banco
        $this->em()->clear();
        $this->holder()->enter(CompanyContext::forUser($this->companyA, $this->userA));
    }

    public function test_find_pelo_id_de_outra_empresa_nao_acha(): void
    {
        $users = self::getContainer()->get(UserRepository::class);

        self::assertTrue($users->get($this->userA)->companyId()->equals($this->companyA));

        $this->expectException(UserNotFound::class);
        $users->get($this->userB);
    }

    public function test_a_propria_empresa_de_outro_nao_e_visivel(): void
    {
        $companies = self::getContainer()->get(CompanyRepository::class);

        self::assertFalse($companies->slugExists(Slug::fromString('cantina-da-lu')));

        $this->expectException(CompanyNotFound::class);
        $companies->get($this->companyB);
    }

    public function test_dql_sem_where_so_traz_a_empresa_do_contexto(): void
    {
        $users = $this->em()->createQuery('SELECT u FROM '.User::class.' u')->getResult();
        $roleCount = $this->em()->createQuery('SELECT COUNT(r.id) FROM '.Role::class.' r')->getSingleScalarResult();

        self::assertSame([$this->userA->toString()], array_map(static fn (User $u): string => $u->id()->toString(), $users));
        self::assertSame(9, (int) $roleCount, 'só os 9 papéis padrão da empresa A');
    }

    public function test_join_tambem_e_filtrado(): void
    {
        // forçando o id de B no WHERE: o filtro entra junto (AND company_id = A)
        $found = $this->em()->createQuery(
            'SELECT u, ur, r FROM '.User::class.' u JOIN u.roles ur JOIN ur.role r WHERE u.id = :id',
        )->setParameter('id', $this->userB, 'user_id')->getResult();

        self::assertSame([], $found);
    }

    public function test_sair_do_contexto_desliga_o_filtro(): void
    {
        $this->holder()->reset();

        self::assertFalse($this->holder()->has());
        self::assertTrue(self::getContainer()->get(UserRepository::class)->get($this->userB)->companyId()->equals($this->companyB));
    }

    public function test_nao_troca_de_empresa_no_meio_da_requisicao(): void
    {
        $this->expectException(\LogicException::class);
        $this->holder()->enter(CompanyContext::forUser($this->companyB, $this->userB));
    }

    /** @return array{CompanyId, UserId} */
    private function registerCompanyWithUser(string $name, string $slug): array
    {
        $companyId = CompanyId::generate();
        $userId = UserId::generate();
        $bus = self::getContainer()->get(CommandBus::class);

        $bus->dispatch(new RegisterCompany($companyId->toString(), $name, $slug, BranchId::generate()->toString(), 'Matriz'));
        $bus->dispatch(new RegisterUser($userId->toString(), $companyId->toString(), 'Ana', 'ana@'.$slug.'.com', 'segredo123', [new RoleAssignment('CASHIER')]));

        return [$companyId, $userId];
    }

    private function holder(): CompanyContextHolder
    {
        return self::getContainer()->get(CompanyContextHolder::class);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
