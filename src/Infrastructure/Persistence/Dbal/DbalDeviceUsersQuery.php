<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Identity\Auth\DeviceUser;
use App\Application\Identity\Auth\DeviceUsersQuery;
use App\Domain\Identity\DeviceId;
use Doctrine\DBAL\Connection;

/** Read model: SQL direto, devolve DTOs — sem hidratar agregados. */
final readonly class DbalDeviceUsersQuery implements DeviceUsersQuery
{
    public function __construct(private Connection $db)
    {
    }

    public function forDevice(DeviceId $deviceId): array
    {
        $rows = $this->db->fetchAllAssociative(<<<'SQL'
            SELECT DISTINCT u.id, u.name
              FROM devices d
              JOIN users u       ON u.company_id = d.company_id
              JOIN user_roles ur ON ur.user_id = u.id
             WHERE d.id = :device
               AND d.revoked_at IS NULL
               AND u.status = 'ACTIVE'
               AND u.pin_hash IS NOT NULL
               AND (ur.branch_id IS NULL OR ur.branch_id = d.branch_id)
             ORDER BY u.name
            SQL, ['device' => $deviceId->toString()]);

        return array_map(
            static fn (array $r): DeviceUser => new DeviceUser((string) $r['id'], (string) $r['name']),
            $rows,
        );
    }
}
