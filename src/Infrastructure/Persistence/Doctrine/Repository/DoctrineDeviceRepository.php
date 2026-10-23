<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Identity\Device;
use App\Domain\Identity\DeviceId;
use App\Domain\Identity\DeviceNotFound;
use App\Domain\Identity\DeviceRepository;
use App\Domain\Identity\OpaqueToken;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineDeviceRepository implements DeviceRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Device $device): void
    {
        $this->em->persist($device);
    }

    public function get(DeviceId $id): Device
    {
        return $this->em->find(Device::class, $id) ?? throw DeviceNotFound::withId($id);
    }

    public function findByToken(OpaqueToken $token): ?Device
    {
        return $this->em->getRepository(Device::class)->findOneBy(['tokenHash' => $token->hash()]);
    }
}
