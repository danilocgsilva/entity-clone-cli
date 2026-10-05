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
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;
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

            // Show available database connections
            $io->section('Available Database Connections');
            $this->connectionLister->listConnections($io);
            $io->newLine();

            // Get all available connections for selection
            $entityManager = Helpers::createEntityManager();
            $databaseAccesses = $entityManager->getRepository(\Danilocgsilva\EntityClone\Entities\DatabaseAccess::class)->findAll();
            
            if (empty($databaseAccesses)) {
                $io->error('No database connections found.');
                return Command::FAILURE;
            }

            // Show connections with enumeration for source selection
            $io->section('Select Source Database Connection');
            $connectionOptions = [];
            foreach ($databaseAccesses as $index => $databaseAccess) {
                $connectionId = $databaseAccess->getId();
                $connectionName = $databaseAccess->getName() ?? "Connection #{$connectionId}";
                $connectionOptions[] = [
                    'id' => $connectionId,
                    'name' => $connectionName
                ];
                $io->text(($index + 1) . '. ' . $connectionName . ' (ID: ' . $connectionId . ')');
            }

            // Get source connection selection
            $sourceConnectionIndex = $io->ask('Enter the number of the source database connection', null, function ($value) use ($connectionOptions) {
                if (!is_numeric($value) || $value < 1 || $value > count($connectionOptions)) {
                    throw new Exception('Please enter a valid number from the list above.');
                }
                return (int)$value;
            });

            $selectedSourceConnection = $connectionOptions[$sourceConnectionIndex - 1];
            $sourceConnectionId = $selectedSourceConnection['id'];
            
            // Get target connection selection if needed
            if ($targetConnectionId === null) {
                $io->section('Select Target Database Connection');
                foreach ($databaseAccesses as $index => $databaseAccess) {
                    $connectionId = $databaseAccess->getId();
                    $connectionName = $databaseAccess->getName() ?? "Connection #{$connectionId}";
                    $io->text(($index + 1) . '. ' . $connectionName . ' (ID: ' . $connectionId . ')');
                }

                $targetConnectionIndex = $io->ask('Enter the number of the target database connection', null, function ($value) use ($connectionOptions) {
                    if (!is_numeric($value) || $value < 1 || $value > count($connectionOptions)) {
                        throw new Exception('Please enter a valid number from the list above.');
                    }
                    return (int)$value;
                });

                $selectedTargetConnection = $connectionOptions[$targetConnectionIndex - 1];
                $targetConnectionId = $selectedTargetConnection['id'];
            }

            // Get source database name if not provided
            if ($sourceDatabaseName === null) {
                $sourcePdo = Domain::getPdoFromDatabaseAccessId($sourceConnectionId, $entityManager);
                $sourceDatabases = Domain::listDatabases($sourcePdo, true);
                
                if (empty($sourceDatabases)) {
                    $io->error('No databases found in the selected source connection.');
                    return Command::FAILURE;
                }
                
                $io->section('Available Source Databases');
                foreach ($sourceDatabases as $index => $database) {
                    $io->text(($index + 1) . '. ' . $database);
                }
                
                $databaseIndex = $io->ask('Enter the number of the source database', null, function ($value) use ($sourceDatabases) {
                    if (!is_numeric($value) || $value < 1 || $value > count($sourceDatabases)) {
                        throw new Exception('Please enter a valid number from the list above.');
                    }
                    return (int)$value;
                });
                
                $sourceDatabaseName = $sourceDatabases[$databaseIndex - 1];
            }

            // Get target database name if not provided
            if ($targetDatabaseName === null) {
                $targetPdo = Domain::getPdoFromDatabaseAccessId($targetConnectionId, $entityManager);
                $targetDatabases = Domain::listDatabases($targetPdo, true);
                
                if (empty($targetDatabases)) {
                    $io->error('No databases found in the selected target connection.');
                    return Command::FAILURE;
                }
                
                $io->section('Available Target Databases');
                foreach ($targetDatabases as $index => $database) {
                    $io->text(($index + 1) . '. ' . $database);
                }
                
                $databaseIndex = $io->ask('Enter the number of the target database', null, function ($value) use ($targetDatabases) {
                    if (!is_numeric($value) || $value < 1 || $value > count($targetDatabases)) {
                        throw new Exception('Please enter a valid number from the list above.');
                    }
                    return (int)$value;
                });
                
                $targetDatabaseName = $targetDatabases[$databaseIndex - 1];
            }

            $sourcePdo = Domain::getPdoFromDatabaseAccessId($sourceConnectionId, $entityManager);
            $targetPdo = Domain::getPdoFromDatabaseAccessId($targetConnectionId, $entityManager);

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
