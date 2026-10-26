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

    public function __invoke(SymfonyStyle $console): int
    {
        $console->writeln('PHP '.\PHP_VERSION);

        try {
            $console->writeln('MySQL '.$this->database->serverVersion());
        } catch (\RuntimeException $exception) {
            $console->error($exception->getMessage());

            return Command::FAILURE;
        }

        $console->success('OK');

        return Command::SUCCESS;
    }
}
