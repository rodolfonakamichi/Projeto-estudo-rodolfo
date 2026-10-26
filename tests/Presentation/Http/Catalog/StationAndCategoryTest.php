<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Http\Catalog;

use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Identity\RegisterUser\RegisterUser;
use App\Application\Identity\RegisterUser\RoleAssignment;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Capability;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use App\Tests\Presentation\Http\JsonApiClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * M2.1: estações e categorias pela API. Empresa A (bar com cozinha) e empresa B
 * (salão, sem `kitchen`) para provar capability e isolamento.
 */
final class StationAndCategoryTest extends WebTestCase
{
    use JsonApiClient;

    private string $branchA;
    private string $branchB;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::getContainer()->get('cache.rate_limiter')->clear();

        $this->branchA = BranchId::generate()->toString();
        $this->branchB = BranchId::generate()->toString();
        $companyA = CompanyId::generate()->toString();
        $companyB = CompanyId::generate()->toString();

        $bus = self::getContainer()->get(CommandBus::class);
        $bus->dispatch(new RegisterCompany($companyA, 'Bar do Zé', 'bar-do-ze', $this->branchA, 'Matriz', capabilities: [Capability::Tables, Capability::Kitchen]));
        $bus->dispatch(new RegisterCompany($companyB, 'Salão da Lu', 'salao-da-lu', $this->branchB, 'Centro'));
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $companyA, 'Gil', 'gil@bar.com', 'segredo123', [new RoleAssignment('MANAGER', $this->branchA)]));
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $companyA, 'Ana', 'ana@bar.com', 'segredo123', [new RoleAssignment('WAITER', $this->branchA)]));
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $companyB, 'Lu', 'lu@salao.com', 'segredo123', [new RoleAssignment('OWNER')]));
    }

    public function test_gerente_cria_renomeia_e_desativa_estacao(): void
    {
        $manager = $this->login('bar-do-ze', 'gil@bar.com');

        $created = $this->json('POST', "/api/branches/{$this->branchA}/stations", ['name' => 'Cozinha'], $manager);
        self::assertResponseStatusCodeSame(201);
        $this->json('POST', "/api/branches/{$this->branchA}/stations", ['name' => 'Bar'], $manager);

        $this->json('PATCH', "/api/stations/{$created['id']}", ['name' => 'Cozinha Quente', 'active' => false], $manager);
        self::assertResponseStatusCodeSame(204);

        $list = $this->json('GET', "/api/branches/{$this->branchA}/stations", token: $manager);
        self::assertSame([
            ['id' => $list['stations'][0]['id'], 'name' => 'Bar', 'active' => true],
            ['id' => $created['id'], 'name' => 'Cozinha Quente', 'active' => false],
        ], $list['stations']);
    }

    public function test_regras_da_estacao(): void
    {
        $manager = $this->login('bar-do-ze', 'gil@bar.com');
        $this->json('POST', "/api/branches/{$this->branchA}/stations", ['name' => 'Cozinha'], $manager);

        // nome único na filial, sem diferenciar maiúsculas
        $this->json('POST', "/api/branches/{$this->branchA}/stations", ['name' => 'COZINHA'], $manager);
        self::assertResponseStatusCodeSame(422);

        // garçom não configura estação
        $this->json('POST', "/api/branches/{$this->branchA}/stations", ['name' => 'Bar'], $this->login('bar-do-ze', 'ana@bar.com'));
        self::assertResponseStatusCodeSame(403);

        // empresa sem a capability kitchen
        $body = $this->json('POST', "/api/branches/{$this->branchB}/stations", ['name' => 'Bar'], $this->login('salao-da-lu', 'lu@salao.com'));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('kitchen', $body['error']);
    }

    public function test_outra_empresa_nao_ve_nem_mexe(): void
    {
        $manager = $this->login('bar-do-ze', 'gil@bar.com');
        $created = $this->json('POST', "/api/branches/{$this->branchA}/stations", ['name' => 'Cozinha'], $manager);
        $outsider = $this->login('salao-da-lu', 'lu@salao.com');

        // dono da empresa B (papel da empresa toda) na filial da A: 404, não 403
        $this->json('POST', "/api/branches/{$this->branchA}/categories", ['name' => 'Bebidas'], $outsider);
        self::assertResponseStatusCodeSame(404);

        $this->json('PATCH', "/api/stations/{$created['id']}", ['name' => 'Invadida'], $outsider);
        self::assertResponseStatusCodeSame(404);

        self::assertSame([], $this->json('GET', "/api/branches/{$this->branchA}/stations", token: $outsider)['stations']);
    }

    public function test_categorias_ordenam_e_arquivar_tira_do_cardapio(): void
    {
        $manager = $this->login('bar-do-ze', 'gil@bar.com');

        $drinks = $this->json('POST', "/api/branches/{$this->branchA}/categories", ['name' => 'Bebidas', 'sortOrder' => 2], $manager);
        $snacks = $this->json('POST', "/api/branches/{$this->branchA}/categories", ['name' => 'Lanches', 'sortOrder' => 1], $manager);
        self::assertSame(['Lanches', 'Bebidas'], $this->categoryNames($manager));

        $this->json('PATCH', "/api/categories/{$drinks['id']}", ['sortOrder' => 0], $manager);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Bebidas', 'Lanches'], $this->categoryNames($manager));

        $this->json('PATCH', "/api/categories/{$drinks['id']}", ['name' => 'lanches'], $manager);
        self::assertResponseStatusCodeSame(422, 'nome repetido na filial');

        $this->json('DELETE', "/api/categories/{$snacks['id']}", token: $manager);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Bebidas'], $this->categoryNames($manager));

        $this->json('DELETE', "/api/categories/{$snacks['id']}", token: $manager);
        self::assertResponseStatusCodeSame(422, 'já arquivada');
    }

    public function test_garcom_le_o_cardapio_mas_nao_edita(): void
    {
        $this->json('POST', "/api/branches/{$this->branchA}/categories", ['name' => 'Bebidas'], $this->login('bar-do-ze', 'gil@bar.com'));
        $waiter = $this->login('bar-do-ze', 'ana@bar.com');

        self::assertSame(['Bebidas'], $this->categoryNames($waiter));

        $this->json('POST', "/api/branches/{$this->branchA}/categories", ['name' => 'Lanches'], $waiter);
        self::assertResponseStatusCodeSame(403);
    }

    public function test_id_malformado_na_rota_e_404_e_payload_invalido_e_422(): void
    {
        $manager = $this->login('bar-do-ze', 'gil@bar.com');

        $this->json('PATCH', '/api/categories/nao-e-ulid', ['name' => 'X'], $manager);
        self::assertResponseStatusCodeSame(404);

        $body = $this->json('POST', "/api/branches/{$this->branchA}/categories", ['name' => '', 'sortOrder' => -1], $manager);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['name', 'sortOrder'], array_keys($body['fields']));
    }

    /** @return list<string> */
    private function categoryNames(string $token): array
    {
        return array_column($this->json('GET', "/api/branches/{$this->branchA}/categories", token: $token)['categories'], 'name');
    }
}
