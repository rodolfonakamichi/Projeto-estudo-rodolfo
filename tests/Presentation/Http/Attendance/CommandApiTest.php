<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Http\Attendance;

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
 * M3.2: comanda pela API, com os aceites do doc 07 — mesa ocupada não abre de
 * novo; 2× X-Burger com Bacon = (3500 + 500) × 2 = 8000; total recalculado;
 * item cancelado não some.
 */
final class CommandApiTest extends WebTestCase
{
    use JsonApiClient;

    private string $branch;
    private string $waiter;
    private string $manager;
    private string $table12;
    /** @var array<string, mixed> */
    private array $burger;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::getContainer()->get('cache.rate_limiter')->clear();

        $company = CompanyId::generate()->toString();
        $outsider = CompanyId::generate()->toString();
        $this->branch = BranchId::generate()->toString();

        // as duas empresas antes da 1ª requisição (o container guarda o contexto da última)
        $bus = self::getContainer()->get(CommandBus::class);
        $bus->dispatch(new RegisterCompany($company, 'Bar do Zé', 'bar-do-ze', $this->branch, 'Matriz', capabilities: [Capability::Tables, Capability::Kitchen]));
        $bus->dispatch(new RegisterCompany($outsider, 'Salão da Lu', 'salao-da-lu', BranchId::generate()->toString(), 'Centro'));
        foreach (['gil@bar.com' => 'MANAGER', 'ana@bar.com' => 'WAITER', 'kim@bar.com' => 'KITCHEN'] as $email => $role) {
            $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $company, 'Pessoa', $email, 'segredo123', [new RoleAssignment($role, $this->branch)]));
        }
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $outsider, 'Lu', 'lu@salao.com', 'segredo123', [new RoleAssignment('OWNER')]));

        $this->manager = $this->login('bar-do-ze', 'gil@bar.com');
        $this->waiter = $this->login('bar-do-ze', 'ana@bar.com');
        $this->table12 = $this->json('POST', "/api/branches/{$this->branch}/locations", ['type' => 'TABLE', 'label' => 'Mesa 12'], $this->manager)['id'];
        $this->burger = $this->json('POST', "/api/branches/{$this->branch}/products", [
            'name' => 'X-Burger',
            'variants' => [['name' => 'X-Burger', 'priceCents' => 3500]],
            'modifierGroups' => [
                ['name' => 'Ponto', 'minSelect' => 1, 'maxSelect' => 1, 'modifiers' => [['name' => 'Ao ponto'], ['name' => 'Bem passado']]],
                ['name' => 'Adicionais', 'minSelect' => 0, 'maxSelect' => 5, 'modifiers' => [['name' => 'Bacon', 'priceDeltaCents' => 500]]],
            ],
        ], $this->manager);
    }

    /** Aceite M3: abrir MESA 12 → outra tentativa de abrir MESA 12 falha. */
    public function test_aceite_mesa_ocupada_nao_abre_de_novo(): void
    {
        $first = $this->openTable12();
        self::assertResponseStatusCodeSame(201);
        self::assertSame([1, 'MESA', 'Mesa 12', 'OPEN'], [$first['displayNumber'], $first['bindType'], $first['bindLabel'], $first['status']]);

        $again = $this->json('POST', "/api/branches/{$this->branch}/commands", ['bindType' => 'MESA', 'bindRef' => $this->table12], $this->waiter);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('A Mesa 12 já tem a comanda #1 aberta.', $again['error']);

        $counter = $this->json('POST', "/api/branches/{$this->branch}/commands", ['bindType' => 'BALCAO', 'bindRef' => 'João'], $this->waiter);
        self::assertSame(2, $counter['displayNumber'], 'numeração do dia segue');
    }

    /** Aceite M3: 2× X-Burger com Bacon → line_total = (3500 + 500) × 2 = 8000; total recalculado a cada mudança. */
    public function test_aceite_dois_x_burger_com_bacon(): void
    {
        $command = $this->openTable12();

        $withBurger = $this->addBurger($command['id'], quantity: 2, withBacon: true);
        self::assertResponseStatusCodeSame(201);
        $item = $withBurger['items'][0];
        self::assertSame(['X-Burger', 2, 3500, 500, 8000], [$item['name'], $item['quantity'], $item['unitPriceCents'], $item['modifiersTotalCents'], $item['lineTotalCents']]);
        self::assertSame(['Ao ponto', 'Bacon'], array_column($item['modifiers'], 'name'));
        self::assertSame(8000, $withBurger['totalCents']);

        $plain = $this->addBurger($command['id'], quantity: 1, withBacon: false);
        self::assertSame(11500, $plain['totalCents']);

        $changed = $this->json('PATCH', "/api/commands/{$command['id']}/items/{$item['id']}", ['quantity' => 3], $this->waiter);
        self::assertSame(12000, $changed['items'][0]['lineTotalCents']);
        self::assertSame(15500, $changed['totalCents']);
        self::assertSame(15500, $changed['balanceCents']);
    }

    /** Aceite M3: cancelar item → CANCELLED (não some) e sai do total. */
    public function test_aceite_item_cancelado_nao_some(): void
    {
        $command = $this->openTable12();
        $item = $this->addBurger($command['id'], quantity: 1, withBacon: false)['items'][0];

        $this->json('POST', "/api/commands/{$command['id']}/items/{$item['id']}/cancel", ['reason' => ''], $this->waiter);
        self::assertResponseStatusCodeSame(422, 'motivo obrigatório');

        $after = $this->json('POST', "/api/commands/{$command['id']}/items/{$item['id']}/cancel", ['reason' => 'cliente desistiu'], $this->waiter);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $after['items']);
        self::assertSame(['CANCELLED', 'cliente desistiu'], [$after['items'][0]['status'], $after['items'][0]['cancelReason']]);
        self::assertSame(0, $after['totalCents']);
    }

    public function test_escolha_invalida_e_regra_do_catalogo(): void
    {
        $command = $this->openTable12();

        $missingRequired = $this->json('POST', "/api/commands/{$command['id']}/items", [
            'productId' => $this->burger['id'],
            'variantId' => $this->burger['variants'][0]['id'],
        ], $this->waiter);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Escolha uma opção em "Ponto".', $missingRequired['error']);
    }

    public function test_cancelar_comanda_libera_a_mesa(): void
    {
        $command = $this->openTable12();

        $this->json('POST', "/api/commands/{$command['id']}/cancel", ['reason' => 'aberta por engano'], $this->waiter);
        self::assertResponseStatusCodeSame(403, 'garçom não cancela comanda (doc 06)');

        $cancelled = $this->json('POST', "/api/commands/{$command['id']}/cancel", ['reason' => 'aberta por engano'], $this->manager);
        self::assertSame('CANCELLED', $cancelled['status']);
        self::assertSame([], $this->json('GET', "/api/branches/{$this->branch}/commands", token: $this->waiter)['commands']);

        $reopened = $this->openTable12();
        self::assertResponseStatusCodeSame(201);
        self::assertSame(2, $reopened['displayNumber']);

        $this->addBurger($command['id'], quantity: 1, withBacon: false);
        self::assertResponseStatusCodeSame(422, 'cancelada não aceita item');
    }

    public function test_lista_e_acesso(): void
    {
        $command = $this->openTable12();
        $this->addBurger($command['id'], quantity: 2, withBacon: true);

        $list = $this->json('GET', "/api/branches/{$this->branch}/commands", token: $this->manager);
        self::assertSame([[1, 'Mesa 12', 8000, 1]], array_map(
            static fn (array $row): array => [$row['displayNumber'], $row['bindLabel'], $row['totalCents'], $row['activeItems']],
            $list['commands'],
        ));

        $this->json('POST', "/api/branches/{$this->branch}/commands", ['bindType' => 'BALCAO'], $this->login('bar-do-ze', 'kim@bar.com'));
        self::assertResponseStatusCodeSame(403, 'cozinha não abre comanda');

        $outsider = $this->login('salao-da-lu', 'lu@salao.com');
        $this->json('GET', "/api/commands/{$command['id']}", token: $outsider);
        self::assertResponseStatusCodeSame(404);
        $this->json('POST', "/api/branches/{$this->branch}/commands", ['bindType' => 'BALCAO'], $outsider);
        self::assertResponseStatusCodeSame(404);
    }

    /** @return array<string, mixed> */
    private function openTable12(): array
    {
        return $this->json('POST', "/api/branches/{$this->branch}/commands", ['bindType' => 'MESA', 'bindRef' => $this->table12], $this->waiter);
    }

    /** @return array<string, mixed> */
    private function addBurger(string $commandId, int $quantity, bool $withBacon): array
    {
        [$doneness, $extras] = $this->burger['modifierGroups'];
        $modifierIds = [$doneness['modifiers'][0]['id']];
        if ($withBacon) {
            $modifierIds[] = $extras['modifiers'][0]['id'];
        }

        return $this->json('POST', "/api/commands/{$commandId}/items", [
            'productId' => $this->burger['id'],
            'variantId' => $this->burger['variants'][0]['id'],
            'quantity' => $quantity,
            'modifierIds' => $modifierIds,
        ], $this->waiter);
    }
}
