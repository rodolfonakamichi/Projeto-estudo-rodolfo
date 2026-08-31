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

    PDO::class => factory(static function (): PDO {
        $host = $_ENV['DB_HOST'] ?? 'db';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $name = $_ENV['DB_DATABASE'] ?? 'comandas';

        return new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $_ENV['DB_USERNAME'] ?? 'comandas',
            $_ENV['DB_PASSWORD'] ?? 'secret',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,          // erro vira exceção
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // linhas como array associativo
                PDO::ATTR_EMULATE_PREPARES => false,                 // prepared statements de verdade
            ],
        );
    }),
]);

return $builder->build();
