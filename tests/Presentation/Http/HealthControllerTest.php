<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function test_health_responde_200_com_status_ok(): void
    {
        $client = static::createClient();

        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', (string) $client->getResponse()->getContent());
    }
}
