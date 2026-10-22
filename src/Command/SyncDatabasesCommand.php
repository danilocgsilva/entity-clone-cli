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
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\SyncDatabasesDataCollector;

#[AsCommand(
    name: 'app:sync-databases',
    description: 'Sync databases from one connection to another, creating missing databases.'
)]
class SyncDatabasesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('source-connection-id', 's', InputOption::VALUE_REQUIRED, 'Source database connection ID')
            ->addOption('target-connection-id', 't', InputOption::VALUE_REQUIRED, 'Target database connection ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Synchronization');

        try {
            $dataCollector = new SyncDatabasesDataCollector($input, $io);
            $data = $dataCollector->collect();
            
            if (!$data) {
                return Command::FAILURE;
            }

            $sourceConnectionId = $data['sourceConnectionId'];
            $targetConnectionId = $data['targetConnectionId'];

            if ($sourceConnectionId === $targetConnectionId) {
                $io->error('Source and target connection IDs must be different.');
                return Command::FAILURE;
            }

            $results = [];
            foreach (Domain::syncDatabasesBetweenConnections($sourceConnectionId, $targetConnectionId, $data['entityManager']) as $result) {
                $results[] = $result;
            }

            if (empty($results)) {
                $io->success('All databases already exist in the target connection.');
                return Command::SUCCESS;
            }

            $io->section('Synchronization Results:');
            
            $successful = 0;
            $failed = 0;
            
            foreach ($results as $result) {
                if ($result['success']) {
                    $io->success("Database '{$result['database']}' created successfully");
                    $successful++;
                } else {
                    $io->error("Failed to create database '{$result['database']}': " . $result['message']);
                    $failed++;
                }
            }

            $io->section('Summary:');
            $io->writeln(sprintf('Successfully created: %d database(s)', $successful));
            $io->writeln(sprintf('Failed to create: %d database(s)', $failed));

        } catch (\Exception $e) {
            $io->error('Error synchronizing databases: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
