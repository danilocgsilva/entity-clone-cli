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
use Danilocgsilva\EntityClone\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:show-foreign-keys',
    description: 'Show foreign keys for a table in a database connection.'
)]
class ShowForeignKeysCommand extends Command
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

        // Create EntityManager
        $entityManager = EntityManagerFactory::create(
            projectRoot: __DIR__ . '/..',
            entityPaths: [__DIR__ . '/../src/Entities'],
        );

        // Get connection ID
        $connectionId = (int) $input->getOption('connection-id');
        if (!$connectionId) {
            $connectionId = $this->askForConnectionId($input, $output, $io, $entityManager);
            if ($connectionId === null) {
                return Command::FAILURE;
            }
        }

        // Get database name
        $databaseName = $input->getOption('database-name');
        if (!$databaseName) {
            $databaseName = $this->askForDatabaseName($input, $output, $io);
            if ($databaseName === null) {
                return Command::FAILURE;
            }
        }

        // Get table name
        $tableName = $input->getOption('table-name');
        if (!$tableName) {
            $tableName = $this->askForTableName($input, $output, $io, $entityManager, $connectionId, $databaseName);
            if ($tableName === null) {
                return Command::FAILURE;
            }
        }

        try {
            // Get PDO connection
            $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $entityManager);

            // Get foreign keys for the table
            $foreignKeys = Domain::getTableForeignKeys($pdo, $databaseName, $tableName);

            if (empty($foreignKeys)) {
                $io->info("No foreign keys found for table '{$tableName}' in database '{$databaseName}'");
                return Command::SUCCESS;
            }

            // Display results
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

        // Get all database accesses
        $databaseAccesses = $entityManager->getRepository(\Danilocgsilva\EntityClone\Entities\DatabaseAccess::class)->findAll();
        
        if (empty($databaseAccesses)) {
            $io->error('No database connections found. Please register a connection first using app:register-connection command.');
            return null;
        }

        // Display available connections
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
        
        // Ask for connection ID
        $question = new Question('Enter the database connection ID: ');
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

        // Get PDO connection
        $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $entityManager);

        // Get tables from database
        $tables = Domain::listTables($pdo, $databaseName);
        
        if (empty($tables)) {
            $io->error("No tables found in database '{$databaseName}'");
            return null;
        }

        // Display available tables
        $io->section('Available Tables');
        $io->listing($tables);

        // Ask for table name
        $question = new Question('Enter the table name: ');
        $tableName = $helper->ask($input, $output, $question);
        
        if ($tableName === null || trim($tableName) === '') {
            $io->error('Table name cannot be empty. Exiting.');
            return null;
        }
        
        $tableName = trim($tableName);
        
        // Validate table exists
        if (!in_array($tableName, $tables)) {
            $io->error("Table '{$tableName}' not found in database '{$databaseName}'.");
            return null;
        }
        
        return $tableName;
    }
}
