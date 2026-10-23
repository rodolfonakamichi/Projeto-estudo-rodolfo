<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Listener;

use App\Domain\Shared\Timestamped;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Clock\ClockInterface;

/**
 * Preenche created_at/updated_at de toda entidade `Timestamped` no flush
 * (padrão Observer: o Doctrine avisa, o listener reage). O relógio é injetado
 * — em teste dá para congelar o tempo com MockClock.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class TimestampListener
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $now = $this->clock->now();

        foreach ([...$uow->getScheduledEntityInsertions(), ...$uow->getScheduledEntityUpdates()] as $entity) {
            if (!$entity instanceof Timestamped) {
                continue;
            }

            $entity->touch($now);
            // a entidade mudou depois do cálculo do changeset: recalcular
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
        }
    }
}
