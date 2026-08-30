<?php

declare(strict_types=1);

use App\Infrastructure\Http\Pipeline;
use App\Infrastructure\Http\Router;
use DI\ContainerBuilder;

use function DI\factory;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

use function FastRoute\simpleDispatcher;

$builder = new ContainerBuilder();

$builder->addDefinitions([
    // FastRoute precisa ser montado a partir do arquivo de rotas.
    Dispatcher::class => factory(static function (): Dispatcher {
        /** @var callable(RouteCollector): void $routes */
        $routes = require __DIR__ . '/routes.php';

        return simpleDispatcher($routes);
    }),

    // Pipeline: autowiring não adivinha "array $middlewares" nem qual
    // RequestHandlerInterface é o final. Definimos explicitamente.
    Pipeline::class => factory(static fn (Router $router): Pipeline => new Pipeline([], $router)),
]);

return $builder->build();
