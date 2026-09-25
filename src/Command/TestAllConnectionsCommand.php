<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Domain;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;
use Danilocgsilva\EntityCloneCli\Helpers;

#[AsCommand(
    name: 'app:test-all-connections',
    description: 'Test PDO connection to all registered database connections.'
)]
class TestAllConnectionsCommand extends BaseCommand
{
    private DatabaseConnectionLister $connectionLister;

    public function __construct(DatabaseConnectionLister $connectionLister)
    {
        $this->connectionLister = $connectionLister;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Testing All Database Connections');

        try {
            $this->connectionLister->listConnections($io);
            
            $entityManager = Helpers::createEntityManager();
            
            $databaseAccesses = $entityManager->getRepository(DatabaseAccess::class)->findAll();
            
            if (empty($databaseAccesses)) {
                $io->warning('No database connections found.');
                return Command::SUCCESS;
            }

            $successfulConnections = 0;
            $failedConnections = 0;

            foreach ($databaseAccesses as $databaseAccess) {
                $connectionId = $databaseAccess->getId();
                $connectionName = $databaseAccess->getName() ?? "Connection #{$connectionId}";
                
                try {
                    $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $entityManager);
                    $connectionTestResult = Domain::testPdoConnection($pdo);

                    if ($connectionTestResult) {
                        $io->success("PDO connection successful (ID: {$connectionId}, Name: {$connectionName}).");
                        $successfulConnections++;
                    } else {
                        $io->error("PDO connection test failed (ID: {$connectionId}, Name: {$connectionName}).");
                        $failedConnections++;
                    }
                } catch (\Exception $e) {
                    $io->error("Error testing connection ID: {$connectionId}, Name: {$connectionName} - " . $e->getMessage());
                    $failedConnections++;
                }
            }

            $io->section('Test Summary');
            $io->text([
                "Total connections tested: " . count($databaseAccesses),
                "Successful connections: {$successfulConnections}",
                "Failed connections: {$failedConnections}"
            ]);

            return $failedConnections > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error testing database connections: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}