<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Http;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Atalhos para testes da API JSON: login por senha e requisição com Bearer.
 * Quem usa precisa preencher `$this->client` (static::createClient()).
 */
trait JsonApiClient
{
    private KernelBrowser $client;

    private function login(string $company, string $email, string $password = 'segredo123'): string
    {
        $body = $this->json('POST', '/api/auth/login', ['company' => $company, 'email' => $email, 'password' => $password]);
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
