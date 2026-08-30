<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final class JsonResponse
{
    private const int FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data, int $status = 200): ResponseInterface
    {
        return new Response(
            status: $status,
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
            body: json_encode($data, self::FLAGS),
        );
    }
}
