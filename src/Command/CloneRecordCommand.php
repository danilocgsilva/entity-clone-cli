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
use Danilocgsilva\EntityCloneCli\Helpers;
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\CloneRecordDataCollector;
use Exception;

#[AsCommand(
    name: 'app:clone-record',
    description: 'Copy a record from one table to another database.'
)]
class CloneRecordCommand extends BaseCommand
{
    private DatabaseConnectionLister $connectionLister;

    public function __construct(DatabaseConnectionLister $connectionLister)
    {
        $this->connectionLister = $connectionLister;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'source-database-name',
                'sd',
                InputOption::VALUE_REQUIRED,
                'Source database name'
            )
            ->addOption(
                'target-database-name',
                'td',
                InputOption::VALUE_REQUIRED,
                'Target database name'
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
            $dataCollector = new CloneRecordDataCollector($input, $io);
            $data = $dataCollector->collect();
            
            if (!$data) {
                return Command::FAILURE;
            }

            $sourceDatabaseName = $data['sourceDatabaseName'];
            $targetDatabaseName = $data['targetDatabaseName'];
            $tableName = $data['tableName'];
            $recordId = $data['recordId'];
            $sourceConnectionId = $data['sourceConnectionId'];
            $targetConnectionId = $data['targetConnectionId'];

            $io->section('Available Database Connections');
            $this->connectionLister->listConnections($io);
            $io->newLine();

            $sourcePdo = Domain::getPdoFromDatabaseAccessId($sourceConnectionId, Helpers::createEntityManager());
            $targetPdo = Domain::getPdoFromDatabaseAccessId($targetConnectionId, Helpers::createEntityManager());

            $sourceDatabases = Domain::listDatabases($sourcePdo, true);
            if (!in_array($sourceDatabaseName, $sourceDatabases)) {
                $io->error("Source database '{$sourceDatabaseName}' not found");
                return Command::FAILURE;
            }

            $targetDatabases = Domain::listDatabases($targetPdo, true);
            if (!in_array($targetDatabaseName, $targetDatabases)) {
                $io->error("Target database '{$targetDatabaseName}' not found");
                return Command::FAILURE;
            }

            $sourceTables = Domain::listTables($sourcePdo, $sourceDatabaseName);
            if (!in_array($tableName, $sourceTables)) {
                $io->error("Table '{$tableName}' not found in database '{$sourceDatabaseName}'");
                return Command::FAILURE;
            }

            $success = Domain::cloneRecordSecure($sourcePdo, $targetPdo, $tableName, $recordId);

            if ($success) {
                $io->success("Record with ID {$recordId} cloned successfully from table '{$tableName}'");
                return Command::SUCCESS;
            } else {
                $io->error('Failed to clone record');
                return Command::FAILURE;
            }

        } catch (Exception $e) {
            $io->error('Error cloning record: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
