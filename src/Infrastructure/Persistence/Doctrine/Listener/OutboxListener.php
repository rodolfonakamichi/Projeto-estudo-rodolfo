<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Listener;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Shared\AggregateRoot;
use App\Domain\Shared\DomainEvent;
use App\Infrastructure\Persistence\Outbox\StoredEvent;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Transactional Outbox (doc 05): no flush, recolhe os eventos registrados por
 * toda raiz de agregado em memória e os agenda como StoredEvent — o mesmo
 * COMMIT grava estado e eventos. O caso de uso não precisa lembrar de nada.
 *
 * Varre o identity map (e não só as entidades alteradas): um agregado pode
 * registrar evento mudando só uma entidade filha.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class OutboxListener
{
    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $uow = $entityManager->getUnitOfWork();
        $metadata = $entityManager->getClassMetadata(StoredEvent::class);

        $roots = [];
        foreach ([...$uow->getScheduledEntityInsertions(), ...array_merge(...array_values(array_map('array_values', $uow->getIdentityMap())))] as $entity) {
            if ($entity instanceof AggregateRoot) {
                $roots[spl_object_id($entity)] = $entity;
            }
        }

        foreach ($roots as $root) {
            foreach ($root->releaseEvents() as $event) {
                $stored = self::store($root, $event);
                $entityManager->persist($stored);
                $uow->computeChangeSet($metadata, $stored);
            }
        }
    }

    private static function store(AggregateRoot $root, DomainEvent $event): StoredEvent
    {
        $branchId = $root->branchId();

        return new StoredEvent(
            CompanyId::fromString($root->companyId()->toString()),
            null === $branchId ? null : BranchId::fromString($branchId->toString()),
            self::snakeCase((new \ReflectionClass($root))->getShortName()),
            $root->id()->toString(),
            (new \ReflectionClass($event))->getShortName(),
            // de fora da classe, get_object_vars só vê as propriedades públicas: o payload
            get_object_vars($event),
            $event->occurredAt(),
        );
    }

    /** `Command` → `command`, `OrderItem` → `order_item` (doc 03: aggregate_type). */
    private static function snakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}
