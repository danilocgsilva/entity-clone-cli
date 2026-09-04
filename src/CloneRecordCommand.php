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
    name: 'app:clone-record',
    description: 'Copy a record from one table to another database.'
)]
class CloneRecordCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'source-connection-id',
                'sc',
                InputOption::VALUE_REQUIRED,
                'Source database connection ID'
            )
            ->addOption(
                'target-connection-id',
                'tc',
                InputOption::VALUE_REQUIRED,
                'Target database connection ID'
            )
            ->addOption(
                'table-name',
                't',
                InputOption::VALUE_REQUIRED,
                'Table name to clone record from'
            )
            ->addOption(
                'record-id',
                'i',
                InputOption::VALUE_REQUIRED,
                'ID of the record to clone'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Clone Database Record');

        try {
            $entityManager = $this->createEntityManager();

            $sourceConnectionId = (int) $this->requireOption($input, $io, 'source-connection-id', 'Enter source database connection ID');
            if (!$sourceConnectionId) return Command::FAILURE;

            $targetConnectionId = (int) $this->requireOption($input, $io, 'target-connection-id', 'Enter target database connection ID');
            if (!$targetConnectionId) return Command::FAILURE;

            $tableName = $this->requireOption($input, $io, 'table-name', 'Enter table name to clone record from');
            if (!$tableName) return Command::FAILURE;

            $recordId = (int) $this->requireOption($input, $io, 'record-id', 'Enter ID of the record to clone');
            if (!$recordId) return Command::FAILURE;

            // Get PDO connections from database access IDs
            $sourcePdo = Domain::getPdoFromDatabaseAccessId($sourceConnectionId, $entityManager);
            $targetPdo = Domain::getPdoFromDatabaseAccessId($targetConnectionId, $entityManager);

            // Clone the record
            $success = Domain::cloneRecordSecure($sourcePdo, $targetPdo, $tableName, $recordId);

            if ($success) {
                $io->success("Record with ID {$recordId} cloned successfully from table '{$tableName}'");
                return Command::SUCCESS;
            } else {
                $io->error('Failed to clone record');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error('Error cloning record: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
