<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use FastRoute\Dispatcher;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Router implements RequestHandlerInterface
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $routeInfo = $this->dispatcher->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath(),
        );

        return match ($routeInfo[0]) {
            Dispatcher::NOT_FOUND => JsonResponse::create(['error' => 'Rota não encontrada'], 404),
            Dispatcher::METHOD_NOT_ALLOWED => JsonResponse::create(['error' => 'Método não permitido'], 405),
            default => $this->runHandler($routeInfo[1], $routeInfo[2], $request),
        };
    }

    /**
     * @param class-string<RequestHandlerInterface> $handlerClass
     * @param array<string, string>                 $vars
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function runHandler(string $handlerClass, array $vars, ServerRequestInterface $request): ResponseInterface
    {
        foreach ($vars as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        $handler = $this->container->get($handlerClass);

        if (! $handler instanceof RequestHandlerInterface) {
            throw new \RuntimeException("{$handlerClass} não é um RequestHandlerInterface.");
        }

        return $handler->handle($request);
    }
}
