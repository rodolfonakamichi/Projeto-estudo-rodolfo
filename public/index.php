<?php

declare(strict_types=1);

use App\Infrastructure\Http\Pipeline;
use App\Infrastructure\Http\SapiEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Container\ContainerInterface;

/** @var ContainerInterface $container */
$container = require __DIR__ . '/../config/bootstrap.php';

$psr17 = new Psr17Factory();
$request = (new ServerRequestCreator($psr17, $psr17, $psr17, $psr17))->fromGlobals();

$response = $container->get(Pipeline::class)->handle($request);

$container->get(SapiEmitter::class)->emit($response);
