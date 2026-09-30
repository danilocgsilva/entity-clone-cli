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
use PDO;

#[AsCommand(
    name: 'anatomy:list-table-sizes',
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
            
            $databases = $this->getDatabasesList($pdo);
            if (empty($databases)) {
                $io->error("No databases found in the connection.");
                return Command::FAILURE;
            }
            
            $databaseName = $this->selectDatabase($io, $databases);
            if (!$databaseName) {
                return Command::FAILURE;
            }

            $tableSizes = $this->getTableSizes($pdo, $databaseName);
            
            if (empty($tableSizes)) {
                $io->info("No tables found in database '{$databaseName}'.");
                return Command::SUCCESS;
            }

            $this->displayTableSizes($io, $tableSizes);

        } catch (Exception $e) {
            $io->error('Error retrieving table sizes: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function getDatabasesList(PDO $pdo): array
    {
        $stmt = $pdo->prepare('SHOW DATABASES');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function selectDatabase(SymfonyStyle $io, array $databases): ?string
    {
        $io->writeln('Available databases:');
        foreach ($databases as $index => $database) {
            $io->writeln(($index + 1) . '. ' . $database);
        }
        
        return $io->ask('Enter the number of the database you want to list tables from:', null, function ($value) use ($databases) {
            $index = (int) $value - 1;
            if ($index < 0 || $index >= count($databases)) {
                throw new Exception('Invalid database number. Please select a valid number from the list.');
            }
            return $databases[$index];
        });
    }

    private function getTableSizes(PDO $pdo, string $databaseName): array
    {
        $tableSizes = [];
        foreach (Domain::listTableSizes($pdo, $databaseName) as $tableSize) {
            $tableSizes[] = [
                'Table' => $tableSize['table'],
                'Size' => $this->formatFileSize((float) $tableSize['size'])
            ];
        }
        return $tableSizes;
    }

    private function formatFileSize(float $bytes): string
    {
        if ($bytes < 1024) {
            return (int) $bytes . ' bytes';
        } elseif ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 2, '.', '') . ' KB';
        } elseif ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2, '.', '') . ' MB';
        } else {
            return number_format($bytes / (1024 * 1024 * 1024), 2, '.', '') . ' GB';
        }
    }

    private function displayTableSizes(SymfonyStyle $io, array $tableSizes): void
    {
        $io->table(['Table', 'Size'], $tableSizes);
    }
}
