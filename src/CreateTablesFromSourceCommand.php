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
    name: 'db:tables:create-from-source',
    description: 'Create all tables from source database to target database'
)]
class CreateTablesFromSourceCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('source-connection', 's', InputOption::VALUE_OPTIONAL, 'Source connection ID')
            ->addOption('target-connection', 't', InputOption::VALUE_OPTIONAL, 'Target connection ID')
            ->addOption('database-name', 'd', InputOption::VALUE_OPTIONAL, 'Database name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create Tables from Source Database');

        $sourceConnectionId = $this->requireOption($input, $io, 'source-connection', 'Enter source connection ID:');
        if (!$sourceConnectionId) return Command::FAILURE;

        $targetConnectionId = $this->requireOption($input, $io, 'target-connection', 'Enter target connection ID:');
        if (!$targetConnectionId) return Command::FAILURE;

        $databaseName = $this->requireOption($input, $io, 'database-name', 'Enter database name:');
        if (!$databaseName) return Command::FAILURE;

        try {
            $entityManager = $this->createEntityManager();

            // Validate connections exist
            $sourceConnection = $entityManager->getRepository(\Danilocgsilva\EntityClone\Entities\DatabaseAccess::class)
                ->find($sourceConnectionId);
            
            if (!$sourceConnection) {
                throw new \RuntimeException("Source connection with ID {$sourceConnectionId} not found");
            }

            $targetConnection = $entityManager->getRepository(\Danilocgsilva\EntityClone\Entities\DatabaseAccess::class)
                ->find($targetConnectionId);
            
            if (!$targetConnection) {
                throw new \RuntimeException("Target connection with ID {$targetConnectionId} not found");
            }

            // Get all tables from source database
            $sourcePdo = Domain::getPdoFromDatabaseAccessId($sourceConnectionId, $entityManager);
            $tables = Domain::listTables($sourcePdo, $databaseName);

            if (empty($tables)) {
                $io->warning("No tables found in database '{$databaseName}' of source connection");
                return Command::SUCCESS;
            }

            $io->text("Found " . count($tables) . " tables to create:");

            foreach ($tables as $tableName) {
                try {
                    // Create table in target database
                    Domain::createTableFromSource(
                        $sourceConnectionId,
                        $targetConnectionId,
                        $databaseName,
                        $tableName,
                        $entityManager
                    );

                    $io->text("✓ Table '{$tableName}' created successfully");
                } catch (\Exception $e) {
                    $io->warning("Failed to create table '{$tableName}': " . $e->getMessage());
                }
            }

            $io->success("All tables processed from source to target database");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Error processing tables: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
