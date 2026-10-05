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
use Danilocgsilva\EntityCloneCli\Helpers;
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\ListTableEntryCountsDataCollector;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;

#[AsCommand(
    name: 'anatomy:list-table-entry-counts',
    description: 'List all tables and their entry counts from a database connection.'
)]
class ListTableEntryCountsCommand extends BaseCommand
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
        $io->title('Database Table Entry Counts List');

        try {
            $dataCollector = new ListTableEntryCountsDataCollector($input, $io);
            $data = $dataCollector->collect();
            
            if (!$data) {
                return Command::FAILURE;
            }

            $connectionId = $data['connectionId'];
            $databaseAccess = $data['databaseAccess'];

            // First list available connections
            $io->section('Available Database Connections:');
            $this->connectionLister->listConnections($io);
            
            $pdo = Domain::createPdoFromDatabaseConnectionEntity($databaseAccess);

            // List available databases
            $stmt = $pdo->query('SHOW DATABASES');
            $databases = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (empty($databases)) {
                $io->error('No databases found in the selected connection.');
                return Command::FAILURE;
            }

            // Remove system databases
            $userDatabases = array_filter($databases, function($database) {
                return !in_array($database, ['information_schema', 'mysql', 'performance_schema', 'sys']);
            });

            if (empty($userDatabases)) {
                $io->error('No user databases found in the selected connection.');
                return Command::FAILURE;
            }

            // Display available databases with numbers
            $io->section('Available Databases:');
            foreach ($userDatabases as $index => $databaseName) {
                $io->text(($index + 1) . '. ' . $databaseName);
            }
            
            // Ask user to select database by number
            $selectedDatabaseIndex = (int) $io->ask(
                'Please enter the number of the database you want to list tables from',
                null,
                function ($value) use ($userDatabases) {
                    $index = (int) $value - 1;
                    if ($index < 0 || $index >= count($userDatabases)) {
                        throw new \InvalidArgumentException('Please enter a valid database number.');
                    }
                    return $value;
                }
            );
            
            if (!$selectedDatabaseIndex) {
                return Command::FAILURE;
            }

            $databaseName = $userDatabases[$selectedDatabaseIndex - 1];

            // Verify database exists
            $stmt = $pdo->prepare('SHOW DATABASES LIKE ?');
            $stmt->execute([$databaseName]);
            
            if (!$stmt->fetch()) {
                $io->error("Database '{$databaseName}' does not exist.");
                return Command::FAILURE;
            }

            $tableCounts = [];
            foreach (Domain::listTableEntryCounts($pdo, $databaseName) as $tableCount) {
                $tableCounts[] = [
                    'Table' => $tableCount['table'],
                    'Count' => number_format($tableCount['count'], 0)
                ];
            }

            if (empty($tableCounts)) {
                $io->info("No tables found in database '{$databaseName}'.");
                return Command::SUCCESS;
            }

            $io->table(['Table', 'Count'], $tableCounts);

        } catch (\Exception $e) {
            $io->error('Error retrieving table entry counts: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
