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

#[AsCommand(
    name: 'app:get-create-table-statement',
    description: 'Get the CREATE TABLE statement for a given table.'
)]
class GetCreateTableStatementCommand extends BaseCommand
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
        $io->title('Create Table Statement');

        try {
            $connectionId = (int) $this->requireOption($input, $io, 'connection-id', 'Please enter the database connection ID:');
            if (!$connectionId) return Command::FAILURE;

            $databaseName = $this->requireOption($input, $io, 'database-name', 'Please enter the database name:');
            if (!$databaseName) return Command::FAILURE;

            $tableName = $this->requireOption($input, $io, 'table-name', 'Please enter the table name:');
            if (!$tableName) return Command::FAILURE;

            $entityManager = $this->createEntityManager();

            $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $entityManager);
            $pdo->exec("USE `{$databaseName}`");

            $statement = Domain::getCreateTableStatement($pdo, $tableName);

            $io->writeln($statement);

        } catch (\Exception $e) {
            $io->error('Error retrieving create table statement: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
