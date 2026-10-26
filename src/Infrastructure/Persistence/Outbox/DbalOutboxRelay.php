<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Outbox;

use App\Application\Shared\Bus\EventBus;
use App\Application\Shared\Event\OutboxRelay;
use App\Application\Shared\Event\RecordedEvent;
use App\Application\Shared\Event\RelayResult;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Relay do outbox (doc 05): um evento por transação —
 *   SELECT ... FOR UPDATE SKIP LOCKED  (trava SÓ este; outro relay pega o próximo)
 *   despacha no event.bus              (consumidores gravam na mesma transação)
 *   UPDATE published_at                (marca como entregue)
 * Se um consumidor falha, a transação desfaz tudo daquele evento e o relay
 * PARA: os seguintes esperam, para não sair de ordem.
 */
final readonly class DbalOutboxRelay implements OutboxRelay
{
    public function __construct(
        private Connection $db,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function relay(int $limit): RelayResult
    {
        $published = 0;

        while ($published < $limit) {
            $eventId = null;
            try {
                $eventId = $this->db->transactional(fn (): ?string => $this->publishNext());
            } catch (\Throwable $exception) {
                $cause = $exception instanceof HandlerFailedException ? ($exception->getPrevious() ?? $exception) : $exception;

                return new RelayResult($published, $this->nextPendingId(), $cause->getMessage());
            }

            if (null === $eventId) {
                break;
            }
            ++$published;
        }

        return new RelayResult($published);
    }

    private function publishNext(): ?string
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM domain_events WHERE published_at IS NULL ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED',
        );
        if (false === $row) {
            return null;
        }

        $this->events->publish(new RecordedEvent(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['aggregate_type'],
            (string) $row['aggregate_id'],
            (string) $row['company_id'],
            null === $row['branch_id'] ? null : (string) $row['branch_id'],
            (array) json_decode((string) $row['payload'], true, flags: \JSON_THROW_ON_ERROR),
            new \DateTimeImmutable((string) $row['occurred_at'], new \DateTimeZone('UTC')),
        ));

        $this->db->executeStatement('UPDATE domain_events SET published_at = :now WHERE id = :id', [
            'now' => $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            'id' => $row['id'],
        ]);

        return (string) $row['id'];
    }

    private function nextPendingId(): ?string
    {
        $id = $this->db->fetchOne('SELECT id FROM domain_events WHERE published_at IS NULL ORDER BY id LIMIT 1');

        return false === $id ? null : (string) $id;
    }
}
