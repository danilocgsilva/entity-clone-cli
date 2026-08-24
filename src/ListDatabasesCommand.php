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
    name: 'app:list-databases',
    description: 'List all databases from a database connection.'
)]
class ListDatabasesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'connection-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Database connection ID'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Connection Databases List');

        try {
            // Get connection ID from input
            $connectionId = (int) $input->getOption('connection-id');

            if (!$connectionId) {
                $io->error('Connection ID is required. Use --connection-id or -c option.');
                return Command::FAILURE;
            }

            // Create EntityManager
            $entityManager = EntityManagerFactory::create(
                projectRoot: __DIR__ . '/..',
                entityPaths: [__DIR__ . '/../src/Entities'],
            );

            // Get PDO connection from database access ID
            $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $entityManager);

            // Get databases from connection
            $databases = Domain::listDatabases($pdo);

            if (empty($databases)) {
                $io->info("No databases found for connection {$connectionId}.");
                return Command::SUCCESS;
            }

            // Sort databases alphabetically
            sort($databases);

            // Display databases as a list
            $io->listing($databases);

        } catch (\Exception $e) {
            $io->error('Error retrieving databases: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
