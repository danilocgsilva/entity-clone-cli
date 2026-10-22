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
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\TestConnectionDataCollector;
use Exception;

#[AsCommand(
    name: 'app:test-connection',
    description: 'Test PDO connection to a database.'
)]
class TestConnectionCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'connection-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Database connection ID'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Connection Test');

        try {
            $dataCollector = new TestConnectionDataCollector($input, $io);
            $data = $dataCollector->collect();
            
            if (!$data) {
                return Command::FAILURE;
            }

            $connectionId = $data['connectionId'];
            $entityManager = Helpers::createEntityManager();
            
            $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $entityManager);

            $connectionTestResult = Domain::testPdoConnection($pdo);

            if ($connectionTestResult) {
                $io->success("PDO connection successful (connection ID: {$connectionId}).");
                return Command::SUCCESS;
            } else {
                $io->error("PDO connection test failed (connection ID: {$connectionId}).");
                return Command::FAILURE;
            }

        } catch (Exception $e) {
            $io->error('Error testing database connection: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
