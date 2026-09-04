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
use Danilocgsilva\EntityClone\DatabaseWorks;

#[AsCommand(
    name: 'app:compare-tables',
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

            $entityManager = $this->createEntityManager();
            $pdo1 = Domain::getPdoFromDatabaseAccessId($access1->getId(), $entityManager);
            $pdo2 = Domain::getPdoFromDatabaseAccessId($access2->getId(), $entityManager);

            $tables1 = Domain::listTables($pdo1, $databaseName);
            $tables2 = Domain::listTables($pdo2, $databaseName);

            sort($tables1);
            sort($tables2);

            $onlyInFirst  = array_diff($tables1, $tables2);
            $onlyInSecond = array_diff($tables2, $tables1);
            $commonTables = array_intersect($tables1, $tables2);

            $io->section("Comparison Results for '{$databaseName}'");

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

            if (empty($onlyInFirst) && empty($onlyInSecond) && empty($commonTables)) {
                $io->info('Both databases have no tables.');
            } elseif (empty($onlyInFirst) && empty($onlyInSecond)) {
                $io->success('Both connections have identical table sets.');
            }

        } catch (\Exception $e) {
            $io->error('Error comparing tables: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
