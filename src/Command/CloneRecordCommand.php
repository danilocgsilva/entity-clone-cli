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
use Doctrine\ORM\EntityManagerInterface;
use Exception;

#[AsCommand(
    name: 'app:clone-record',
    description: 'Copy a record from one table to another database.'
)]
class CloneRecordCommand extends BaseCommand
{
    private DatabaseConnectionLister $connectionLister;
    private ?EntityManagerInterface $entityManager;

    public function __construct(DatabaseConnectionLister $connectionLister, ?EntityManagerInterface $entityManager = null)
    {
        $this->connectionLister = $connectionLister;
        $this->entityManager = $entityManager;
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
            $em = $this->entityManager ?? Helpers::createEntityManager();
            $dataCollector = $this->makeDataCollector($input, $io, $em);
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

            $sourcePdo = $this->getPdoFromDatabaseAccessId($sourceConnectionId, $em);
            $targetPdo = $this->getPdoFromDatabaseAccessId($targetConnectionId, $em);

            $sourceDatabases = $this->listDatabases($sourcePdo, true);
            if (!in_array($sourceDatabaseName, $sourceDatabases)) {
                $io->error("Source database '{$sourceDatabaseName}' not found");
                return Command::FAILURE;
            }

            $targetDatabases = $this->listDatabases($targetPdo, true);
            if (!in_array($targetDatabaseName, $targetDatabases)) {
                $io->error("Target database '{$targetDatabaseName}' not found");
                return Command::FAILURE;
            }

            $sourceTables = $this->listTables($sourcePdo, $sourceDatabaseName);
            if (!in_array($tableName, $sourceTables)) {
                $io->error("Table '{$tableName}' not found in database '{$sourceDatabaseName}'");
                return Command::FAILURE;
            }

            $success = $this->cloneRecordSecure($sourcePdo, $targetPdo, $tableName, $recordId);

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

    protected function makeDataCollector(InputInterface $input, SymfonyStyle $io, EntityManagerInterface $em): CloneRecordDataCollector
    {
        return new CloneRecordDataCollector($input, $io, $em);
    }

    protected function getPdoFromDatabaseAccessId(int $id, EntityManagerInterface $em): \PDO
    {
        return Domain::getPdoFromDatabaseAccessId($id, $em);
    }

    protected function listDatabases(\PDO $pdo, bool $excludeSystem): array
    {
        return Domain::listDatabases($pdo, $excludeSystem);
    }

    protected function listTables(\PDO $pdo, string $database): array
    {
        return Domain::listTables($pdo, $database);
    }

    protected function cloneRecordSecure(\PDO $source, \PDO $target, string $table, string|int $id): bool
    {
        return Domain::cloneRecordSecure($source, $target, $table, $id);
    }
}
