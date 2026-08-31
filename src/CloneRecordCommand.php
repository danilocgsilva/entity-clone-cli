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
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityClone\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:clone-record',
    description: 'Copy a record from one table to another database.'
)]
class CloneRecordCommand extends Command
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
            // Create EntityManager
            $entityManager = EntityManagerFactory::create(
                projectRoot: __DIR__ . '/..',
                entityPaths: [__DIR__ . '/../src/Entities'],
            );

            // Get connection IDs with prompting if not provided
            $sourceConnectionId = (int) $input->getOption('source-connection-id');
            if (!$sourceConnectionId) {
                $sourceConnectionId = (int) $io->ask('Enter source database connection ID');
                if (!$sourceConnectionId) {
                    $io->error('Source connection ID is required.');
                    return Command::FAILURE;
                }
            }

            $targetConnectionId = (int) $input->getOption('target-connection-id');
            if (!$targetConnectionId) {
                $targetConnectionId = (int) $io->ask('Enter target database connection ID');
                if (!$targetConnectionId) {
                    $io->error('Target connection ID is required.');
                    return Command::FAILURE;
                }
            }

            // Get table name with prompting if not provided
            $tableName = $input->getOption('table-name');
            if (!$tableName) {
                $tableName = $io->ask('Enter table name to clone record from');
                if (!$tableName) {
                    $io->error('Table name is required.');
                    return Command::FAILURE;
                }
            }

            // Get record ID with prompting if not provided
            $recordId = (int) $input->getOption('record-id');
            if (!$recordId) {
                $recordId = (int) $io->ask('Enter ID of the record to clone');
                if (!$recordId) {
                    $io->error('Record ID is required.');
                    return Command::FAILURE;
                }
            }

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
