<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\DatabaseWorks;

#[AsCommand(
    name: 'app:create-missing-database',
    description: 'List databases present in the first connection but missing in the second, and optionally create them.'
)]
class CreateMissingDatabaseCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('connection-id-1', null, InputOption::VALUE_REQUIRED, 'First database connection ID')
            ->addOption('connection-id-2', null, InputOption::VALUE_REQUIRED, 'Second database connection ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Missing Databases');

        try {
            [$access1, $access2] = $this->resolveTwoConnections($input, $io) ?? [null, null];
            if (!$access1 || !$access2) {
                return Command::FAILURE;
            }

            $databaseWorks1 = new DatabaseWorks($access1);
            $databaseWorks2 = new DatabaseWorks($access2);

            $missing = array_values(array_diff(
                $databaseWorks1->listDatabasesNames(),
                $databaseWorks2->listDatabasesNames()
            ));

            if (empty($missing)) {
                $io->success('No missing databases. The second connection has all databases from the first.');
                return Command::SUCCESS;
            }

            $io->section('Databases in connection 1 missing from connection 2:');
            foreach ($missing as $i => $db) {
                $io->writeln(sprintf('%d. %s', $i + 1, $db));
            }

            $pick = (int) $io->ask('Enter the number of the database to create in connection 2 (0 to cancel)');
            if ($pick < 1 || $pick > count($missing)) {
                $io->warning('No database created.');
                return Command::SUCCESS;
            }

            $dbName = $missing[$pick - 1];
            $databaseWorks2->createDatabase($dbName);
            $io->success("Database '{$dbName}' created in connection 2.");

        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
