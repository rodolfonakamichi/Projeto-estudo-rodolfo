<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Shared\Event\OutboxRelay;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * bin/console app:outbox:publish [--limit=500] — despacha os eventos pendentes
 * do outbox (doc 05). No MVP roda por cron a cada minuto; depois vira worker.
 */
#[AsCommand(name: 'app:outbox:publish', description: 'Despacha os eventos pendentes do outbox para os consumidores')]
final readonly class OutboxPublishCommand
{
    public function __construct(private OutboxRelay $relay)
    {
    }

    public function __invoke(SymfonyStyle $console, #[Option('Máximo de eventos nesta rodada')] int $limit = 500): int
    {
        $result = $this->relay->relay($limit);

        if (null !== $result->error) {
            $console->error(\sprintf('%d evento(s) despachado(s); parou no %s: %s', $result->published, $result->failedEventId ?? '?', $result->error));

            return Command::FAILURE;
        }

        $console->success(\sprintf('%d evento(s) despachado(s).', $result->published));

        return Command::SUCCESS;
    }
}
