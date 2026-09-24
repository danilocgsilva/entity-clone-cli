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
use Exception;
use RuntimeException;
use Danilocgsilva\EntityClone\Exceptions\MissingTargetDatabase;
use Danilocgsilva\EntityClone\Exceptions\TargetTableAlreadyExists;

#[AsCommand(
    name: 'db:table:create-from-source',
    description: 'Create a table in target database from source database structure'
)]
class CreateTableFromSourceCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('source-connection', 's', InputOption::VALUE_OPTIONAL, 'Source connection ID')
            ->addOption('target-connection', 't', InputOption::VALUE_OPTIONAL, 'Target connection ID')
            ->addOption('database-name', 'd', InputOption::VALUE_OPTIONAL, 'Database name')
            ->addOption('table-name', null, InputOption::VALUE_OPTIONAL, 'Table name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create Table from Source Database');

        $options = $this->initializeOptions($input, $io);
        if ($options === null) {
            return Command::FAILURE;
        }

        [$sourceConnectionId, $targetConnectionId, $databaseName, $tableName] = $options;

        try {
            $entityManager = $this->createEntityManager();

            $sourceConnection = $entityManager->getRepository(\Danilocgsilva\EntityClone\Entities\DatabaseAccess::class)
                ->find($sourceConnectionId);

            if (!$sourceConnection) {
                throw new RuntimeException("Source connection with ID {$sourceConnectionId} not found");
            }

            $targetConnection = $entityManager->getRepository(\Danilocgsilva\EntityClone\Entities\DatabaseAccess::class)
                ->find($targetConnectionId);

            if (!$targetConnection) {
                throw new RuntimeException("Target connection with ID {$targetConnectionId} not found");
            }

            Domain::createTableFromSource(
                (int) $sourceConnectionId,
                (int) $targetConnectionId,
                $databaseName,
                $tableName,
                $entityManager
            );

            $io->success("Table '{$tableName}' successfully created in database '{$databaseName}' from source connection");
            return Command::SUCCESS;
        } catch (TargetTableAlreadyExists $e) {
            $io->warning("Table '{$tableName}' already exists in database '{$databaseName}'. No action taken.");
            return Command::SUCCESS;
        } catch (MissingTargetDatabase $e) {
            var_dump(get_class($e)); // This will show you exactly what class is being thrown
            $io->error($e->getMessage());
            return Command::FAILURE;
        } catch (Exception $e) {
            var_dump(get_class($e)); // This will show you exactly what class is being thrown
            $io->error("Error creating table: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function initializeOptions(InputInterface $input, SymfonyStyle $io): ?array
    {
        $sourceConnectionId = $this->requireOption($input, $io, 'source-connection', 'Enter source connection ID:');
        if (!$sourceConnectionId) {
            return null;
        }

        $targetConnectionId = $this->requireOption($input, $io, 'target-connection', 'Enter target connection ID:');
        if (!$targetConnectionId) {
            return null;
        }

        $databaseName = $this->requireOption($input, $io, 'database-name', 'Enter database name:');
        if (!$databaseName) {
            return null;
        }

        $tableName = $this->requireOption($input, $io, 'table-name', 'Enter table name:');
        if (!$tableName) {
            return null;
        }

        return [
            (int) $sourceConnectionId,
            (int) $targetConnectionId,
            $databaseName,
            $tableName
        ];
    }
}
