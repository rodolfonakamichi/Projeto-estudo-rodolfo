<?php

declare(strict_types=1);

use App\Presentation\Http\HealthController;
use FastRoute\RouteCollector;

return static function (RouteCollector $r): void {
    $r->addRoute('GET', '/health', HealthController::class);
};
