<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Domain;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;
use Danilocgsilva\EntityCloneCli\Helpers;

#[AsCommand(
    name: 'app:list-databases',
    description: 'List all databases from a database connection.'
)]
class ListDatabasesCommand extends BaseCommand
{
    private DatabaseConnectionLister $connectionLister;

    public function __construct(DatabaseConnectionLister $connectionLister)
    {
        $this->connectionLister = $connectionLister;
        parent::__construct();
    }

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
            $this->connectionLister->listConnections($io);
            
            $connectionId = (int) $this->requireOption($input, $io, 'connection-id', 'Please enter the database connection ID');
            if (!$connectionId) return Command::FAILURE;

            $entityManager = Helpers::createEntityManager();

            $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $entityManager);

            $databases = Domain::listDatabases($pdo, true);

            if (empty($databases)) {
                $io->info("No databases found for connection {$connectionId}.");
                return Command::SUCCESS;
            }

            sort($databases);

            $io->listing($databases);

        } catch (\Exception $e) {
            $io->error('Error retrieving databases: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
