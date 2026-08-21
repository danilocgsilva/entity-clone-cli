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
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityClone\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:list-tables',
    description: 'List all tables from a database.'
)]
class ListTablesCommand extends Command
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
            $connectionId = (int) $input->getOption('connection-id');
            $databaseName = $input->getOption('database-name');

            if (!$connectionId) {
                $io->error('Connection ID is required. Use --connection-id or -c option.');
                return Command::FAILURE;
            }

            if (!$databaseName) {
                $io->error('Database name is required. Use --database-name or -d option.');
                return Command::FAILURE;
            }

            // Create EntityManager
            $entityManager = EntityManagerFactory::create(
                projectRoot: __DIR__ . '/..',
                entityPaths: [__DIR__ . '/../src/Entities'],
            );

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
