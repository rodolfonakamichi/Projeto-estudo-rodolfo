<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:health', description: 'Verifica se a aplicação inicializa')]
final class HealthCommand extends Command
{
    public function __construct(private readonly \PDO $pdo)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>OK</info>');
        $output->writeln('PHP ' . PHP_VERSION);

        try {
            $version = $this->pdo->query('SELECT VERSION()')->fetchColumn();
            $output->writeln("MySQL {$version}");
        } catch (\PDOException $e) {
            $output->writeln("<error>Banco inacessível: {$e->getMessage()}</error>");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
