<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Pipeline implements RequestHandlerInterface
{
    /** @var list<MiddlewareInterface> */
    private array $queue;

    /**
     * @param list<MiddlewareInterface> $middlewares
     */
    public function __construct(
        array $middlewares,
        private readonly RequestHandlerInterface $finalHandler,
    ) {
        $this->queue = $middlewares;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = array_shift($this->queue);

        if ($middleware === null) {
            return $this->finalHandler->handle($request);
        }

        // cada middleware recebe "o resto do pipeline" ($this) como próximo handler
        return $middleware->process($request, $this);
    }
}
