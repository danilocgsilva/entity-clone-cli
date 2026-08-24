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
use Danilocgsilva\EntityClone\EntityManagerFactory;

#[AsCommand(
    name: 'app:compare-tables',
    description: 'Compare tables between two database connections.'
)]
class CompareTablesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'connection-id-1',
                'c1',
                InputOption::VALUE_REQUIRED,
                'First database connection ID'
            )
            ->addOption(
                'connection-id-2',
                'c2',
                InputOption::VALUE_REQUIRED,
                'Second database connection ID'
            )
            ->addOption(
                'database-name',
                'd',
                InputOption::VALUE_REQUIRED,
                'Database name to compare tables from'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Tables Comparison');

        try {
            // Get connection IDs and database name from input
            $connectionId1 = (int) $input->getOption('connection-id-1');
            $connectionId2 = (int) $input->getOption('connection-id-2');
            $databaseName = $input->getOption('database-name');

            // Validate required options
            if (!$connectionId1) {
                $io->error('First connection ID is required. Use --connection-id-1 or -c1 option.');
                return Command::FAILURE;
            }

            if (!$connectionId2) {
                $io->error('Second connection ID is required. Use --connection-id-2 or -c2 option.');
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

            // Get PDO connections from database access IDs
            $pdo1 = Domain::getPdoFromDatabaseAccessId($connectionId1, $entityManager);
            $pdo2 = Domain::getPdoFromDatabaseAccessId($connectionId2, $entityManager);

            // Get tables from both databases
            $tables1 = Domain::listTables($pdo1, $databaseName);
            $tables2 = Domain::listTables($pdo2, $databaseName);

            // Sort tables alphabetically
            sort($tables1);
            sort($tables2);

            // Find differences
            $onlyInFirst = array_diff($tables1, $tables2);
            $onlyInSecond = array_diff($tables2, $tables1);
            $commonTables = array_intersect($tables1, $tables2);

            // Display results
            $io->section('Comparison Results');
            
            if (empty($tables1) && empty($tables2)) {
                $io->info("No tables found in database '{$databaseName}' for both connections.");
                return Command::SUCCESS;
            }

            if (!empty($onlyInFirst)) {
                $io->section('Tables only in first connection:');
                $io->listing($onlyInFirst);
            }

            if (!empty($onlyInSecond)) {
                $io->section('Tables only in second connection:');
                $io->listing($onlyInSecond);
            }

            if (!empty($commonTables)) {
                $io->section('Common tables:');
                $io->listing($commonTables);
            }

            // If there are differences, provide a summary
            if (!empty($onlyInFirst) || !empty($onlyInSecond)) {
                $io->section('Summary');
                $io->writeln(sprintf(
                    'First connection (%d): %d tables',
                    $connectionId1,
                    count($tables1)
                ));
                $io->writeln(sprintf(
                    'Second connection (%d): %d tables',
                    $connectionId2,
                    count($tables2)
                ));
                
                if (!empty($onlyInFirst)) {
                    $io->writeln(sprintf(
                        'Only in first connection: %d tables',
                        count($onlyInFirst)
                    ));
                }
                
                if (!empty($onlyInSecond)) {
                    $io->writeln(sprintf(
                        'Only in second connection: %d tables',
                        count($onlyInSecond)
                    ));
                }
            } else {
                $io->success('Both connections have identical table sets.');
            }

        } catch (\Exception $e) {
            $io->error('Error comparing tables: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
