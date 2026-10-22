<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;
use Exception;

class SyncDatabasesDataCollector extends BaseDataCollector
{
    private DatabaseConnectionLister $connectionLister;

    public function __construct(InputInterface $input, SymfonyStyle $io, DatabaseConnectionLister $connectionLister)
    {
        $this->connectionLister = $connectionLister;
        parent::__construct($input, $io);
    }

    public function collect(): ?array
    {
        try {
            // First list all connections
            $this->connectionLister->listConnections($this->io);
            
            $sourceConnectionId = (int) $this->requireOption('source-connection-id', 'Please enter the source database connection ID');
            if (!$sourceConnectionId) return null;

            $targetConnectionId = (int) $this->requireOption('target-connection-id', 'Please enter the target database connection ID');
            if (!$targetConnectionId) return null;

            $entityManager = Helpers::createEntityManager();

            // Validate connections exist
            $repository = $entityManager->getRepository(DatabaseAccess::class);
            $sourceConnection = $repository->find($sourceConnectionId);
            if (!$sourceConnection) {
                $this->io->error("Source connection ID {$sourceConnectionId} not found.");
                return null;
            }

            $targetConnection = $repository->find($targetConnectionId);
            if (!$targetConnection) {
                $this->io->error("Target connection ID {$targetConnectionId} not found.");
                return null;
            }

            return [
                'sourceConnectionId' => $sourceConnectionId,
                'targetConnectionId' => $targetConnectionId,
                'entityManager' => $entityManager
            ];
        } catch (Exception $e) {
            $this->io->error('Error collecting database synchronization data: ' . $e->getMessage());
            return null;
        }
    }
}
