<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Http\Auth;

use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Identity\RegisterUser\RegisterUser;
use App\Application\Identity\RegisterUser\RoleAssignment;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Autenticação ponta a ponta pela API (kernel + Security + MySQL de teste).
 */
final class AuthFlowTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private KernelBrowser $client;
    private BranchId $branchId;
    private UserId $waiterId;

    protected function setUp(): void
    {
        self::mockTime('2026-10-23 08:00:00');
        $this->client = static::createClient();
        // contador do rate limiter mora no cache (arquivo): zera para os testes não interferirem entre si
        self::getContainer()->get('cache.rate_limiter')->clear();

        $companyId = CompanyId::generate();
        $this->branchId = BranchId::generate();
        $this->waiterId = UserId::generate();

        $bus = self::getContainer()->get(CommandBus::class);
        $bus->dispatch(new RegisterCompany($companyId->toString(), 'Bar do Zé', 'bar-do-ze', $this->branchId->toString(), 'Matriz'));
        $bus->dispatch(new RegisterUser($this->waiterId->toString(), $companyId->toString(), 'Ana', 'ana@bar.com', 'segredo123', [new RoleAssignment('WAITER', $this->branchId->toString())], '1234'));
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $companyId->toString(), 'Gil', 'gil@bar.com', 'segredo123', [new RoleAssignment('MANAGER')]));
    }

    public function test_login_com_senha_e_me(): void
    {
        $token = $this->login('ana@bar.com');

        $me = $this->json('GET', '/api/me', token: $token);

        self::assertResponseIsSuccessful();
        self::assertSame('ana@bar.com', $me['email']);
        self::assertSame('WAITER', $me['roles'][0]['code']);
    }

    public function test_credencial_errada_e_mensagem_generica(): void
    {
        $wrongPassword = $this->json('POST', '/api/auth/login', ['company' => 'bar-do-ze', 'email' => 'ana@bar.com', 'password' => 'errada']);
        self::assertResponseStatusCodeSame(401);
        $unknownCompany = $this->json('POST', '/api/auth/login', ['company' => 'nao-existe', 'email' => 'ana@bar.com', 'password' => 'segredo123']);
        self::assertResponseStatusCodeSame(401);

        self::assertSame($wrongPassword, $unknownCompany, 'não revela o que estava errado');
    }

    public function test_bloqueia_apos_5_falhas(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->json('POST', '/api/auth/login', ['company' => 'bar-do-ze', 'email' => 'ana@bar.com', 'password' => "chute{$i}"]);
            self::assertResponseStatusCodeSame(401);
        }

        $this->json('POST', '/api/auth/login', ['company' => 'bar-do-ze', 'email' => 'ana@bar.com', 'password' => 'segredo123']);
        self::assertResponseStatusCodeSame(429);
    }

    public function test_token_expira_apos_12h_ociosas(): void
    {
        $token = $this->login('ana@bar.com');

        self::mockTime('+11 hours');
        $this->json('GET', '/api/me', token: $token);
        self::assertResponseIsSuccessful();

        self::mockTime('+11 hours'); // 11h depois do último uso: ainda vale (deslizante)
        $this->json('GET', '/api/me', token: $token);
        self::assertResponseIsSuccessful();

        self::mockTime('+13 hours');
        $this->json('GET', '/api/me', token: $token);
        self::assertResponseStatusCodeSame(401);
    }

    public function test_logout_revoga_o_token(): void
    {
        $token = $this->login('ana@bar.com');

        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);
        self::assertResponseStatusCodeSame(204);

        $this->json('GET', '/api/me', token: $token);
        self::assertResponseStatusCodeSame(401);
    }

    public function test_pareamento_e_login_por_pin(): void
    {
        // WAITER não pareia aparelho
        $this->json('POST', '/api/devices', ['branchId' => $this->branchId->toString(), 'name' => 'Tablet'], $this->login('ana@bar.com'));
        self::assertResponseStatusCodeSame(403);

        // MANAGER pareia
        $paired = $this->json('POST', '/api/devices', ['branchId' => $this->branchId->toString(), 'name' => 'Tablet'], $this->login('gil@bar.com'));
        self::assertResponseStatusCodeSame(201);
        $device = $paired['token'];

        // tela de PIN: só quem tem PIN e papel na filial (Gil não tem PIN)
        $users = $this->json('GET', '/api/device/users', token: $device);
        self::assertSame([['id' => $this->waiterId->toString(), 'name' => 'Ana']], $users['users']);

        $this->json('POST', '/api/auth/pin', ['userId' => $this->waiterId->toString(), 'pin' => '0000'], $device);
        self::assertResponseStatusCodeSame(401);

        $pin = $this->json('POST', '/api/auth/pin', ['userId' => $this->waiterId->toString(), 'pin' => '1234'], $device);
        self::assertResponseStatusCodeSame(201);

        $this->json('GET', '/api/me', token: $pin['token']);
        self::assertResponseIsSuccessful();
    }

    public function test_token_de_aparelho_nao_acessa_rotas_de_usuario_e_vice_versa(): void
    {
        $paired = $this->json('POST', '/api/devices', ['branchId' => $this->branchId->toString(), 'name' => 'Tablet'], $this->login('gil@bar.com'));

        $this->json('GET', '/api/me', token: $paired['token']);
        self::assertResponseStatusCodeSame(403);

        $this->json('GET', '/api/device/users', token: $this->login('gil@bar.com'));
        self::assertResponseStatusCodeSame(403);
    }

    public function test_payload_invalido_lista_os_campos(): void
    {
        $body = $this->json('POST', '/api/auth/login', ['company' => '', 'email' => 'x', 'password' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['company', 'email', 'password'], array_keys($body['fields']));
    }

    private function login(string $email): string
    {
        $body = $this->json('POST', '/api/auth/login', ['company' => 'bar-do-ze', 'email' => $email, 'password' => 'segredo123']);
        self::assertResponseStatusCodeSame(201);

        return $body['token'];
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed>
     */
    private function json(string $method, string $uri, ?array $payload = null, ?string $token = null): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = "Bearer {$token}";
        }

        $this->client->request($method, $uri, server: $server, content: null === $payload ? null : json_encode($payload, \JSON_THROW_ON_ERROR));

        $content = (string) $this->client->getResponse()->getContent();

        return '' === $content ? [] : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }
}
