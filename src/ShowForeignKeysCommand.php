<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

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
                'Database name'
            )
            ->addOption(
                'table-name',
                't',
                InputOption::VALUE_REQUIRED,
                'Table name'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Foreign Keys Information');

        $entityManager = $this->createEntityManager();

        $connectionId = (int) $input->getOption('connection-id');
        if (!$connectionId) {
            $connectionId = $this->askForConnectionId($input, $output, $io, $entityManager);
            if ($connectionId === null) {
                return Command::FAILURE;
            }
        }

        $databaseName = $input->getOption('database-name');
        if (!$databaseName) {
            $databaseName = $this->askForDatabaseName($input, $output, $io);
            if ($databaseName === null) {
                return Command::FAILURE;
            }
        }

        $tableName = $input->getOption('table-name');
        if (!$tableName) {
            $tableName = $this->askForTableName($input, $output, $io, $entityManager, $connectionId, $databaseName);
            if ($tableName === null) {
                return Command::FAILURE;
            }
        }

        try {
            $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $entityManager);

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

    private function askForConnectionId(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        EntityManagerInterface $entityManager
    ): ?int {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper */
        $helper = $this->getHelper('question');

        $databaseAccesses = $entityManager->getRepository(\Danilocgsilva\EntityClone\Entities\DatabaseAccess::class)->findAll();
        
        if (empty($databaseAccesses)) {
            $io->error('No database connections found. Please register a connection first using app:register-connection command.');
            return null;
        }

        $io->section('Available Database Connections');
        $connections = [];
        foreach ($databaseAccesses as $access) {
            $connections[] = [
                $access->getId(),
                $access->getName(),
                $access->getHost() . ':' . $access->getPort()
            ];
        }
        
        $io->table(['ID', 'Name', 'Host:Port'], $connections);
        
        $question = new Question('Enter the database connection ID');
        $connectionId = $helper->ask($input, $output, $question);
        
        if ($connectionId === null || !is_numeric($connectionId)) {
            $io->error('Invalid connection ID. Exiting.');
            return null;
        }
        
        return (int) $connectionId;
    }

    private function askForDatabaseName(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io
    ): ?string {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper */
        $helper = $this->getHelper('question');

        $question = new Question('Enter the database name: ');
        $databaseName = $helper->ask($input, $output, $question);
        
        if ($databaseName === null || trim($databaseName) === '') {
            $io->error('Database name cannot be empty. Exiting.');
            return null;
        }
        
        return trim($databaseName);
    }

    private function askForTableName(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        EntityManagerInterface $entityManager,
        int $connectionId,
        string $databaseName
    ): ?string {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper */
        $helper = $this->getHelper('question');

        $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $entityManager);

        $tables = Domain::listTables($pdo, $databaseName);
        
        if (empty($tables)) {
            $io->error("No tables found in database '{$databaseName}'");
            return null;
        }

        $io->section('Available Tables');
        $numberedTables = [];
        foreach ($tables as $index => $table) {
            $numberedTables[] = [$index + 1, $table];
        }
        $io->table(['Number', 'Table Name'], $numberedTables);

        $question = new Question('Enter the table number: ');
        $tableSelection = $helper->ask($input, $output, $question);
        
        if ($tableSelection === null || !is_numeric($tableSelection)) {
            $io->error('Invalid table selection. Please enter a valid number. Exiting.');
            return null;
        }
        
        $tableIndex = (int) $tableSelection - 1;
        
        if (!isset($tables[$tableIndex])) {
            $io->error("Table with number '{$tableSelection}' not found.");
            return null;
        }
        
        return $tables[$tableIndex];
    }
}