<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Domain;

#[AsCommand(
    name: 'app:list-tables',
    description: 'List all tables from a database.'
)]
class ListTablesCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'connection-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Database connection ID'
            )
            ->addOption(
                'database-name',
                'd',
                InputOption::VALUE_REQUIRED,
                'Database name to list tables from'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Tables List');

        try {
            // Get connection ID and database name from input
            $connectionId = (int) $this->requireOption($input, $io, 'connection-id', 'Please enter the database connection ID');
            if (!$connectionId) return Command::FAILURE;

            $databaseName = $this->requireOption($input, $io, 'database-name', 'Please enter the database name');
            if (!$databaseName) return Command::FAILURE;

            $entityManager = $this->createEntityManager();

            // Get PDO connection from database access ID
            $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $entityManager);

            // Get tables from database
            $tables = Domain::listTables($pdo, $databaseName);

            if (empty($tables)) {
                $io->info("No tables found in database '{$databaseName}' for connection {$connectionId}.");
                return Command::SUCCESS;
            }

            // Sort tables alphabetically
            sort($tables);

            // Display tables as a list
            $io->listing($tables);

        } catch (\Exception $e) {
            $io->error('Error retrieving database tables: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
