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
use Exception;

#[AsCommand(
    name: 'anatomy:list-table-entry-counts',
    description: 'List all tables and their entry counts from a database connection.'
)]
class ListTableEntryCountsCommand extends BaseCommand
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
                'Database name to list tables from'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Table Entry Counts List');

        try {
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

            $databaseName = $this->requireOption($input, $io, 'database-name', 'Please enter the database name:');
            if (!$databaseName) {
                return Command::FAILURE;
            }

            $pdo = Domain::createPdoFromDatabaseConnectionEntity($databaseAccess);

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

        } catch (Exception $e) {
            $io->error('Error retrieving table entry counts: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}