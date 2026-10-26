<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use App\Application\Health\DatabaseProbe;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

final readonly class DbalDatabaseProbe implements DatabaseProbe
{
    public function __construct(private Connection $connection)
    {
    }

    public function serverVersion(): string
    {
        try {
            return (string) $this->connection->fetchOne('SELECT VERSION()');
        } catch (DbalException $exception) {
            throw new \RuntimeException('Banco inacessível: '.$exception->getMessage(), previous: $exception);
        }
    }
}
