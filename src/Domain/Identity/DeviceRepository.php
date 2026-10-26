<?php

declare(strict_types=1);

namespace App\Domain\Identity;

interface DeviceRepository
{
    public function save(Device $device): void;

    public function get(DeviceId $id): Device;

    public function findByToken(OpaqueToken $token): ?Device;
}
