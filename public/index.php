<?php

declare(strict_types=1);

use App\Infrastructure\Http\Pipeline;
use App\Infrastructure\Http\Router;
use App\Infrastructure\Http\SapiEmitter;
use FastRoute\RouteCollector;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

use function FastRoute\simpleDispatcher;

require __DIR__ . '/../vendor/autoload.php';

// Fábricas PSR-17 — criam Request, Response, Stream, Uri.
// O Psr17Factory do nyholm implementa TODAS as fábricas, por isso ele
// aparece 4x no ServerRequestCreator (request, uri, uploadedFile, stream).
$psr17 = new Psr17Factory();

// ServerRequest (PSR-7) a partir de $_SERVER, $_GET, $_POST, php://input...
$request = (new ServerRequestCreator($psr17, $psr17, $psr17, $psr17))->fromGlobals();

// Roteador: compila as rotas de config/routes.php num dispatcher FastRoute.
/** @var callable(RouteCollector): void $routes */
$routes = require __DIR__ . '/../config/routes.php';
$router = new Router(simpleDispatcher($routes));

// Pipeline: sem middlewares ainda; o Router é o handler final.
$pipeline = new Pipeline([], $router);

// Processa a request pelo pipeline e emite o response.
$response = $pipeline->handle($request);
(new SapiEmitter())->emit($response);
