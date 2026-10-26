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

/** M3.1: mesas e outros locais da filial. Empresa B (salão) não tem a capability `tables`. */
final class LocationApiTest extends WebTestCase
{
    use JsonApiClient;

    private string $branch;
    private string $salonBranch;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::getContainer()->get('cache.rate_limiter')->clear();

        $company = CompanyId::generate()->toString();
        $salon = CompanyId::generate()->toString();
        $this->branch = BranchId::generate()->toString();
        $this->salonBranch = BranchId::generate()->toString();

        $bus = self::getContainer()->get(CommandBus::class);
        $bus->dispatch(new RegisterCompany($company, 'Bar do Zé', 'bar-do-ze', $this->branch, 'Matriz', capabilities: [Capability::Tables]));
        $bus->dispatch(new RegisterCompany($salon, 'Salão da Lu', 'salao-da-lu', $this->salonBranch, 'Centro'));
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $company, 'Gil', 'gil@bar.com', 'segredo123', [new RoleAssignment('MANAGER', $this->branch)]));
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $company, 'Ana', 'ana@bar.com', 'segredo123', [new RoleAssignment('WAITER', $this->branch)]));
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $salon, 'Lu', 'lu@salao.com', 'segredo123', [new RoleAssignment('OWNER')]));
    }

    public function test_gerente_cadastra_mesas_e_balcao(): void
    {
        $manager = $this->login('bar-do-ze', 'gil@bar.com');

        $table = $this->json('POST', "/api/branches/{$this->branch}/locations", ['type' => 'TABLE', 'label' => 'Mesa 12'], $manager);
        self::assertResponseStatusCodeSame(201);
        $this->json('POST', "/api/branches/{$this->branch}/locations", ['type' => 'COUNTER', 'label' => 'Balcão'], $manager);

        $this->json('PATCH', "/api/locations/{$table['id']}", ['label' => 'Mesa 12 (varanda)', 'active' => false], $manager);
        self::assertResponseStatusCodeSame(204);

        $list = $this->json('GET', "/api/branches/{$this->branch}/locations", token: $this->login('bar-do-ze', 'ana@bar.com'));
        self::assertSame([['COUNTER', 'Balcão', true], ['TABLE', 'Mesa 12 (varanda)', false]], array_map(
            static fn (array $location): array => [$location['type'], $location['label'], $location['active']],
            $list['locations'],
        ));
    }

    public function test_regras_de_cadastro(): void
    {
        $manager = $this->login('bar-do-ze', 'gil@bar.com');
        $this->json('POST', "/api/branches/{$this->branch}/locations", ['type' => 'TABLE', 'label' => 'Mesa 1'], $manager);

        $this->json('POST', "/api/branches/{$this->branch}/locations", ['type' => 'TABLE', 'label' => 'MESA 1'], $manager);
        self::assertResponseStatusCodeSame(422, 'rótulo único na filial');

        $invalid = $this->json('POST', "/api/branches/{$this->branch}/locations", ['type' => 'SOFA', 'label' => 'Sofá'], $manager);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('TABLE, COUNTER, ROOM, EVENT_AREA', $invalid['error']);

        $room = $this->json('POST', "/api/branches/{$this->branch}/locations", ['type' => 'ROOM', 'label' => 'Quarto 101'], $manager);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('hotel', $room['error']);

        $this->json('POST', "/api/branches/{$this->branch}/locations", ['type' => 'TABLE', 'label' => 'Mesa 2'], $this->login('bar-do-ze', 'ana@bar.com'));
        self::assertResponseStatusCodeSame(403, 'garçom não cadastra mesa');
    }

    public function test_sem_capability_tables_so_balcao_e_area(): void
    {
        $owner = $this->login('salao-da-lu', 'lu@salao.com');

        $this->json('POST', "/api/branches/{$this->salonBranch}/locations", ['type' => 'TABLE', 'label' => 'Mesa 1'], $owner);
        self::assertResponseStatusCodeSame(422);

        $this->json('POST', "/api/branches/{$this->salonBranch}/locations", ['type' => 'COUNTER', 'label' => 'Recepção'], $owner);
        self::assertResponseStatusCodeSame(201);
    }

    public function test_outra_empresa_nao_mexe(): void
    {
        $table = $this->json('POST', "/api/branches/{$this->branch}/locations", ['type' => 'TABLE', 'label' => 'Mesa 1'], $this->login('bar-do-ze', 'gil@bar.com'));
        $outsider = $this->login('salao-da-lu', 'lu@salao.com');

        $this->json('PATCH', "/api/locations/{$table['id']}", ['active' => false], $outsider);
        self::assertResponseStatusCodeSame(404);

        $this->json('POST', "/api/branches/{$this->branch}/locations", ['type' => 'COUNTER', 'label' => 'Invasão'], $outsider);
        self::assertResponseStatusCodeSame(404);
    }
}
