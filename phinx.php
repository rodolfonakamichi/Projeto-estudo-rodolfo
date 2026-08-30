<?php

declare(strict_types=1);

require __DIR__ . '/config/env.php';

return [
    'paths' => [
        'migrations' => __DIR__ . '/database/migrations',
        'seeds' => __DIR__ . '/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'local',
        'local' => [
            'adapter' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'db',
            'name' => $_ENV['DB_DATABASE'] ?? 'comandas',
            'user' => $_ENV['DB_USERNAME'] ?? 'comandas',
            'pass' => $_ENV['DB_PASSWORD'] ?? 'secret',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'charset' => 'utf8mb4',
        ],
    ],
    'version_order' => 'creation',
];
