<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Health\DatabaseProbe;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:health', description: 'Verifica se a aplicação inicializa e o banco responde')]
final readonly class HealthCommand
{
    public function __construct(private DatabaseProbe $database)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->writeln('PHP '.\PHP_VERSION);

        try {
            $io->writeln('MySQL '.$this->database->serverVersion());
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('OK');

        return Command::SUCCESS;
    }
}
