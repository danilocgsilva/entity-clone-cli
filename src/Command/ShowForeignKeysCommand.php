<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Danilocgsilva\EntityClone\Domain;

#[AsCommand(
    name: 'app:show-foreign-keys',
    description: 'Show foreign keys for a table in a database connection.'
)]
class ShowForeignKeysCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('connection-id', 'c', InputOption::VALUE_REQUIRED, 'Database connection ID')
            ->addOption('database-name', 'd', InputOption::VALUE_REQUIRED, 'Database name')
            ->addOption('table-name', 't', InputOption::VALUE_REQUIRED, 'Table name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Foreign Keys Information');

        $entityManager = $this->createEntityManager();

        $options = $this->initializeOptions($input, $io);
        if ($options === null) {
            return Command::FAILURE;
        }

        [$connectionId, $databaseName, $tableName] = $options;

        try {
            $pdo = Domain::getPdoFromDatabaseAccessId((int) $connectionId, $entityManager);

            $foreignKeys = Domain::getTableForeignKeys($pdo, $databaseName, $tableName);

            if (empty($foreignKeys)) {
                $io->info("No foreign keys found for table '{$tableName}' in database '{$databaseName}'");
                return Command::SUCCESS;
            }

            $io->section("Foreign Keys for table '{$tableName}'");
            
            $tableRows = [];
            foreach ($foreignKeys as $foreignKey) {
                $tableRows[] = [
                    $foreignKey['column_name'],
                    $foreignKey['referenced_table_name'],
                    $foreignKey['referenced_column_name']
                ];
            }
            
            $io->table(
                ['Column Name', 'Referenced Table', 'Referenced Column'],
                $tableRows
            );

        } catch (\Exception $e) {
            $io->error('Error retrieving foreign keys: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function initializeOptions(InputInterface $input, SymfonyStyle $io): ?array
    {
        
        $connectionId = $this->requireOption($input, $io, 'connection-id', 'Enter connection ID:');
        if (!$connectionId) {
            return null;
        }

        $databaseName = $this->requireOption($input, $io, 'database-name', 'Enter database name:');
        if (!$databaseName) {
            return null;
        }

        $tableName = $this->requireOption($input, $io, 'table-name', 'Enter table name:');
        if (!$tableName) {
            return null;
        }

        return [
            (int) $connectionId,
            $databaseName,
            $tableName
        ];
    }
}
