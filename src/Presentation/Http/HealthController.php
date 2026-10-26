<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liveness: responde 200 se a aplicação sobe. Não toca no banco — quem quiser
 * checar o MySQL usa `bin/console app:health`.
 */
final class HealthController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
