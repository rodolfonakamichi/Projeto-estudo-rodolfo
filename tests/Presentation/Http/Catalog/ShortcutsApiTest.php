<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Http\Catalog;

use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Identity\RegisterUser\RegisterUser;
use App\Application\Identity\RegisterUser\RoleAssignment;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use App\Tests\Presentation\Http\JsonApiClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** M2.5: atalhos do operador — favoritos por pessoa e filial; recentes/mais vendidos vazios até o M3. */
final class ShortcutsApiTest extends WebTestCase
{
    use JsonApiClient;

    private string $branch;
    private string $uri;
    private string $chopp;
    private string $burger;
    private string $water;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::getContainer()->get('cache.rate_limiter')->clear();

        $this->branch = BranchId::generate()->toString();
        $this->uri = "/api/branches/{$this->branch}/shortcuts";
        $company = CompanyId::generate()->toString();
        $outsiderCompany = CompanyId::generate()->toString();
        $outsiderBranch = BranchId::generate()->toString();

        // as duas empresas antes da 1ª requisição (o container guarda o contexto da última)
        $bus = self::getContainer()->get(CommandBus::class);
        $bus->dispatch(new RegisterCompany($company, 'Bar do Zé', 'bar-do-ze', $this->branch, 'Matriz'));
        $bus->dispatch(new RegisterCompany($outsiderCompany, 'Salão da Lu', 'salao-da-lu', $outsiderBranch, 'Centro'));
        foreach (['gil@bar.com' => 'MANAGER', 'ana@bar.com' => 'WAITER', 'caio@bar.com' => 'CASHIER', 'kim@bar.com' => 'KITCHEN'] as $email => $role) {
            $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $company, 'Pessoa', $email, 'segredo123', [new RoleAssignment($role, $this->branch)]));
        }
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $outsiderCompany, 'Lu', 'lu@salao.com', 'segredo123', [new RoleAssignment('OWNER')]));

        $manager = $this->login('bar-do-ze', 'gil@bar.com');
        $this->chopp = $this->createProduct($manager, $this->branch, 'Chopp');
        $this->burger = $this->createProduct($manager, $this->branch, 'X-Burger');
        $this->water = $this->createProduct($manager, $this->branch, 'Água');
    }

    public function test_operador_monta_e_reordena_os_favoritos(): void
    {
        $waiter = $this->login('bar-do-ze', 'ana@bar.com');

        $empty = $this->json('GET', $this->uri, token: $waiter);
        self::assertSame(['favorites' => [], 'recent' => [], 'topSelling' => []], $empty);

        $set = $this->json('PUT', "{$this->uri}/favorites", ['productIds' => [$this->burger, $this->chopp]], $waiter);
        self::assertResponseIsSuccessful();
        self::assertSame(['X-Burger', 'Chopp'], array_column($set['favorites'], 'name'));

        // mesma lista em outra ordem + um novo: atualiza posições em vez de recriar (PK composta)
        $reordered = $this->json('PUT', "{$this->uri}/favorites", ['productIds' => [$this->chopp, $this->water, $this->burger]], $waiter);
        self::assertResponseIsSuccessful();
        self::assertSame(['Chopp', 'Água', 'X-Burger'], array_column($reordered['favorites'], 'name'));

        self::assertSame(['Chopp', 'Água', 'X-Burger'], array_column($this->json('GET', $this->uri, token: $waiter)['favorites'], 'name'));
    }

    public function test_cada_operador_tem_a_sua_lista(): void
    {
        $this->json('PUT', "{$this->uri}/favorites", ['productIds' => [$this->chopp]], $this->login('bar-do-ze', 'ana@bar.com'));

        $cashier = $this->login('bar-do-ze', 'caio@bar.com');
        self::assertSame([], $this->json('GET', $this->uri, token: $cashier)['favorites']);
    }

    public function test_produto_desativado_some_dos_favoritos_e_nao_pode_ser_fixado(): void
    {
        $waiter = $this->login('bar-do-ze', 'ana@bar.com');
        $this->json('PUT', "{$this->uri}/favorites", ['productIds' => [$this->chopp, $this->burger]], $waiter);

        $this->json('PATCH', "/api/products/{$this->chopp}", ['active' => false], $this->login('bar-do-ze', 'gil@bar.com'));

        self::assertSame(['X-Burger'], array_column($this->json('GET', $this->uri, token: $waiter)['favorites'], 'name'));

        $this->json('PUT', "{$this->uri}/favorites", ['productIds' => [$this->chopp]], $waiter);
        self::assertResponseStatusCodeSame(422);
    }

    public function test_regras_de_acesso_e_validacao(): void
    {
        // cozinha não lança item: não tem atalho de venda
        $this->json('PUT', "{$this->uri}/favorites", ['productIds' => [$this->chopp]], $this->login('bar-do-ze', 'kim@bar.com'));
        self::assertResponseStatusCodeSame(403);

        $waiter = $this->login('bar-do-ze', 'ana@bar.com');

        $this->json('PUT', "{$this->uri}/favorites", ['productIds' => [$this->chopp, $this->chopp]], $waiter);
        self::assertResponseStatusCodeSame(422, 'repetido');

        $this->json('PUT', "{$this->uri}/favorites", ['productIds' => ['nao-e-ulid']], $waiter);
        self::assertResponseStatusCodeSame(422, 'ID malformado');

        // dono de outra empresa (papel da empresa toda) nesta filial: 404, não 403
        $this->json('PUT', "{$this->uri}/favorites", ['productIds' => [$this->chopp]], $this->login('salao-da-lu', 'lu@salao.com'));
        self::assertResponseStatusCodeSame(404);
    }

    private function createProduct(string $token, string $branch, string $name): string
    {
        $product = $this->json('POST', "/api/branches/{$branch}/products", ['name' => $name, 'variants' => [['name' => $name, 'priceCents' => 1000]]], $token);
        self::assertResponseStatusCodeSame(201);

        return $product['id'];
    }
}
