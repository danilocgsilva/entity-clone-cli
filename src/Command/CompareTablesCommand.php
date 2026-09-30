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
use Danilocgsilva\EntityClone\DatabaseWorks;
use Danilocgsilva\EntityCloneCli\Helpers;

#[AsCommand(
    name: 'anatomy:compare-tables',
    description: 'Compare tables between two database connections.'
)]
class CompareTablesCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('connection-id-1', 'c1', InputOption::VALUE_REQUIRED, 'First database connection ID')
            ->addOption('connection-id-2', 'c2', InputOption::VALUE_REQUIRED, 'Second database connection ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Tables Comparison');

        try {
            [$access1, $access2] = $this->resolveTwoConnections($input, $io) ?? [null, null];
            if (!$access1 || !$access2) {
                return Command::FAILURE;
            }

            $commonDatabases = array_values(array_intersect(
                (new DatabaseWorks($access1))->listDatabasesNames(),
                (new DatabaseWorks($access2))->listDatabasesNames()
            ));

            if (empty($commonDatabases)) {
                $io->error('No common databases found between the two connections.');
                return Command::FAILURE;
            }

            sort($commonDatabases);
            $io->section('Common databases:');
            foreach ($commonDatabases as $i => $db) {
                $io->writeln(sprintf('%d. %s', $i + 1, $db));
            }

            $pick = (int) $io->ask('Pick a database number to compare tables');
            if ($pick < 1 || $pick > count($commonDatabases)) {
                $io->error('Invalid selection.');
                return Command::FAILURE;
            }

            $databaseName = $commonDatabases[$pick - 1];

            $entityManager = Helpers::createEntityManager();
            $pdo1 = Domain::getPdoFromDatabaseAccessId($access1->getId(), $entityManager);
            $pdo2 = Domain::getPdoFromDatabaseAccessId($access2->getId(), $entityManager);

            $tables1 = Domain::listTables($pdo1, $databaseName);
            $tables2 = Domain::listTables($pdo2, $databaseName);

            sort($tables1);
            sort($tables2);

            $onlyInFirst  = array_diff($tables1, $tables2);
            $onlyInSecond = array_diff($tables2, $tables1);
            $commonTables = array_intersect($tables1, $tables2);

            $this->displayTableComparison($io, $tables1, $tables2, $databaseName);
            $this->displayCategorySections($io, $onlyInFirst, $onlyInSecond, $commonTables);
            $this->displaySummary($io, $tables1, $tables2, $onlyInFirst, $onlyInSecond, $commonTables);

        } catch (\Exception $e) {
            $io->error('Error comparing tables: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function displayTableComparison(SymfonyStyle $io, array $tables1, array $tables2, string $databaseName): void
    {
        // Create a combined table with all tables
        $allTables = array_unique(array_merge($tables1, $tables2));
        sort($allTables);

        $tableData = [];
        foreach ($allTables as $table) {
            $existsInFirst = in_array($table, $tables1) ? '✓' : '✗';
            $existsInSecond = in_array($table, $tables2) ? '✓' : '✗';
            $tableData[] = [$table, $existsInFirst, $existsInSecond];
        }

        // Display results in table format
        $io->section("Comparison Results for '{$databaseName}'");
        $io->table(
            ['Table Name', 'Exists in Connection 1', 'Exists in Connection 2'],
            $tableData
        );
    }

    private function displayCategorySections(SymfonyStyle $io, array $onlyInFirst, array $onlyInSecond, array $commonTables): void
    {
        $io->section('Tables present just at the first connection');
        if (!empty($onlyInFirst)) {
            $io->listing($onlyInFirst);
        } else {
            $io->text('No tables found');
        }

        $io->section('Tables present just at the second connection');
        if (!empty($onlyInSecond)) {
            $io->listing($onlyInSecond);
        } else {
            $io->text('No tables found');
        }

        $io->section('Tables present at both connections');
        if (!empty($commonTables)) {
            $io->listing($commonTables);
        } else {
            $io->text('No tables found');
        }
    }

    private function displaySummary(SymfonyStyle $io, array $tables1, array $tables2, array $onlyInFirst, array $onlyInSecond, array $commonTables): void
    {
        // Show summary statistics
        $allTables = array_unique(array_merge($tables1, $tables2));
        $totalTables = count($allTables);
        $onlyInFirstCount = count($onlyInFirst);
        $onlyInSecondCount = count($onlyInSecond);
        $commonCount = count($commonTables);

        $io->section('Summary');
        $io->writeln(sprintf('Total tables: %d', $totalTables));
        $io->writeln(sprintf('Only in connection 1: %d', $onlyInFirstCount));
        $io->writeln(sprintf('Only in connection 2: %d', $onlyInSecondCount));
        $io->writeln(sprintf('Common to both: %d', $commonCount));

        if (empty($onlyInFirst) && empty($onlyInSecond) && empty($commonTables)) {
            $io->info('Both databases have no tables.');
        } elseif (empty($onlyInFirst) && empty($onlyInSecond)) {
            $io->success('Both connections have identical table sets.');
        }
    }
}
