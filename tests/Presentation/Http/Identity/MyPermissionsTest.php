<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Http\Identity;

use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Identity\RegisterUser\RegisterUser;
use App\Application\Identity\RegisterUser\RoleAssignment;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Aceite M1.5 (doc 07): WAITER recebe 403 em `command.close`; CASHIER passa.
 * Pela API, passando pelo Security → PermissionVoter → PermissionChecker → User::can.
 */
final class MyPermissionsTest extends WebTestCase
{
    private KernelBrowser $client;
    private BranchId $branchId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::getContainer()->get('cache.rate_limiter')->clear();

        $companyId = CompanyId::generate();
        $this->branchId = BranchId::generate();
        $branch = $this->branchId->toString();

        $bus = self::getContainer()->get(CommandBus::class);
        $bus->dispatch(new RegisterCompany($companyId->toString(), 'Bar do Zé', 'bar-do-ze', $branch, 'Matriz'));
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $companyId->toString(), 'Ana', 'ana@bar.com', 'segredo123', [new RoleAssignment('WAITER', $branch)]));
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $companyId->toString(), 'Caio', 'caio@bar.com', 'segredo123', [new RoleAssignment('CASHIER', $branch)]));
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $companyId->toString(), 'Gil', 'gil@bar.com', 'segredo123', [new RoleAssignment('MANAGER')]));
    }

    public function test_waiter_403_e_cashier_204_em_command_close(): void
    {
        $uri = '/api/me/permissions/command.close?branchId='.$this->branchId;

        $denied = $this->get($uri, $this->login('ana@bar.com'));
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Sem permissão: command.close.', $denied['error']);

        $this->get($uri, $this->login('caio@bar.com'));
        self::assertResponseStatusCodeSame(204);
    }

    public function test_papel_de_filial_nao_vale_no_nivel_da_empresa(): void
    {
        $this->get('/api/me/permissions/command.open', $this->login('ana@bar.com'));
        self::assertResponseStatusCodeSame(403);
    }

    public function test_lista_as_permissoes_efetivas_na_filial(): void
    {
        $token = $this->login('ana@bar.com');

        $inBranch = $this->get('/api/me/permissions?branchId='.$this->branchId, $token);
        self::assertResponseIsSuccessful();
        self::assertContains('command.open', $inBranch['permissions']);
        self::assertNotContains('command.close', $inBranch['permissions']);

        $companyLevel = $this->get('/api/me/permissions', $token);
        self::assertSame([], $companyLevel['permissions']);
    }

    public function test_papel_da_empresa_toda_vale_em_qualquer_filial(): void
    {
        $this->get('/api/me/permissions/admin.device.manage?branchId='.$this->branchId, $this->login('gil@bar.com'));
        self::assertResponseStatusCodeSame(204);
    }

    public function test_codigo_inexistente_e_404_e_filial_malformada_e_422(): void
    {
        $token = $this->login('caio@bar.com');

        $this->get('/api/me/permissions/command.fly', $token);
        self::assertResponseStatusCodeSame(404);

        $this->get('/api/me/permissions/command.close?branchId=xyz', $token);
        self::assertResponseStatusCodeSame(422);
    }

    private function login(string $email): string
    {
        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['company' => 'bar-do-ze', 'email' => $email, 'password' => 'segredo123'],
            \JSON_THROW_ON_ERROR,
        ));
        self::assertResponseStatusCodeSame(201);

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['token'];
    }

    /** @return array<string, mixed> */
    private function get(string $uri, string $token): array
    {
        $this->client->request('GET', $uri, server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);
        $content = (string) $this->client->getResponse()->getContent();

        return '' === $content ? [] : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }
}
