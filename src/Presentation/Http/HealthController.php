<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Infrastructure\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HealthController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return JsonResponse::create(['status' => 'ok']);
    }
}
