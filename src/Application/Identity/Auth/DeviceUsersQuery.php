<?php

declare(strict_types=1);

namespace App\Application\Identity\Auth;

use App\Domain\Identity\DeviceId;

/**
 * Lado de LEITURA (CQRS): a lista da tela de PIN não precisa montar agregados
 * — um SELECT direto para um DTO é mais simples e mais rápido.
 */
interface DeviceUsersQuery
{
    /** @return list<DeviceUser> usuários ativos, com PIN, com papel na filial do aparelho */
    public function forDevice(DeviceId $deviceId): array;
}
