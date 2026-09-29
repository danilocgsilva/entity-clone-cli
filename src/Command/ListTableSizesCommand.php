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
use Exception;

#[AsCommand(
    name: 'app:list-table-sizes',
    description: 'List all tables and their sizes from a database connection.'
)]
class ListTableSizesCommand extends BaseCommand
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
        $io->title('Database Table Sizes List');

        try {
            // First list all connections
            $this->connectionLister->listConnections($io);

            $entityManager = Helpers::createEntityManager();
            $repository = $entityManager->getRepository(DatabaseAccess::class);

            $connectionId = (int) $this->requireOption($input, $io, 'connection-id', 'Please enter the database connection ID:');
            if (!$connectionId) {
                return Command::FAILURE;
            }

            $databaseAccess = $repository->find($connectionId);
            if (!$databaseAccess) {
                $io->error("Database connection with ID {$connectionId} not found.");
                return Command::FAILURE;
            }

            $pdo = Domain::createPdoFromDatabaseConnectionEntity($databaseAccess);

            // List available databases
            $stmt = $pdo->prepare('SHOW DATABASES');
            $stmt->execute();
            
            $databases = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (empty($databases)) {
                $io->error("No databases found in the connection.");
                return Command::FAILURE;
            }

            // Show numbered list of databases
            $io->writeln('Available databases:');
            foreach ($databases as $index => $database) {
                $io->writeln(($index + 1) . '. ' . $database);
            }
            
            // Ask user to select database by number
            $databaseChoice = $io->ask('Enter the number of the database you want to list tables from:', null, function ($value) use ($databases) {
                $index = (int) $value - 1;
                if ($index < 0 || $index >= count($databases)) {
                    throw new Exception('Invalid database number. Please select a valid number from the list.');
                }
                return $databases[$index];
            });

            if (!$databaseChoice) {
                return Command::FAILURE;
            }

            $databaseName = $databaseChoice;

            // Verify database exists
            $stmt = $pdo->prepare('SHOW DATABASES LIKE ?');
            $stmt->execute([$databaseName]);
            
            if (!$stmt->fetch()) {
                $io->error("Database '{$databaseName}' does not exist.");
                return Command::FAILURE;
            }

            $tableSizes = [];
            foreach (Domain::listTableSizes($pdo, $databaseName) as $tableSize) {
                $tableSizes[] = [
                    'Table' => $tableSize['table'],
                    'Size' => number_format($tableSize['size'], 2) . ' bytes'
                ];
            }

            if (empty($tableSizes)) {
                $io->info("No tables found in database '{$databaseName}'.");
                return Command::SUCCESS;
            }

            $io->table(['Table', 'Size'], $tableSizes);

        } catch (Exception $e) {
            $io->error('Error retrieving table sizes: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
