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
use Exception;
use RuntimeException;
use Danilocgsilva\EntityClone\Exceptions\MissingTargetDatabase;
use Danilocgsilva\EntityClone\Exceptions\TargetTableAlreadyExists;
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\CreateTableFromSourceDataCollector;

#[AsCommand(
    name: 'app:table:create-from-source',
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

        try {
            $dataCollector = new CreateTableFromSourceDataCollector($input, $io);
            $data = $dataCollector->collect();
            
            if (!$data) {
                return Command::FAILURE;
            }

            $sourceConnectionId = $data['sourceConnectionId'];
            $targetConnectionId = $data['targetConnectionId'];
            $databaseName = $data['databaseName'];
            $tableName = $data['tableName'];

            Domain::createTableFromSource(
                (int) $sourceConnectionId,
                (int) $targetConnectionId,
                $databaseName,
                $tableName,
                $this->entityManager
            );

            $io->success("Table '{$tableName}' successfully created in database '{$databaseName}' from source connection");
            return Command::SUCCESS;
        } catch (TargetTableAlreadyExists $e) {
            $io->warning("Table '{$tableName}' already exists in database '{$databaseName}'. No action taken.");
            return Command::SUCCESS;
        } catch (MissingTargetDatabase $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        } catch (Exception $e) {
            $io->error("Error creating table: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
