<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use PDO;

class CreateTableFromSourceDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        try {
            $sourceConnectionId = (int) $this->requireOption('source-connection', 'Enter source connection ID:');
            if (!$sourceConnectionId) return null;

            $targetConnectionId = (int) $this->requireOption('target-connection', 'Enter target connection ID:');
            if (!$targetConnectionId) return null;

            $databaseName = $this->requireOption('database-name', 'Enter database name:');
            if (!$databaseName) return null;

            $tableName = $this->requireOption('table-name', 'Enter table name:');
            if (!$tableName) return null;

            // Validate connections exist
            $repository = $this->entityManager->getRepository(DatabaseAccess::class);
            
            $sourceConnection = $repository->find($sourceConnectionId);
            if (!$sourceConnection) {
                $this->io->error("Source connection with ID {$sourceConnectionId} not found.");
                return null;
            }

            $targetConnection = $repository->find($targetConnectionId);
            if (!$targetConnection) {
                $this->io->error("Target connection with ID {$targetConnectionId} not found.");
                return null;
            }

            return [
                'sourceConnectionId' => $sourceConnectionId,
                'targetConnectionId' => $targetConnectionId,
                'databaseName' => $databaseName,
                'tableName' => $tableName,
                'sourceConnection' => $sourceConnection,
                'targetConnection' => $targetConnection
            ];
        } catch (\Exception $e) {
            $this->io->error('Error collecting create table data: ' . $e->getMessage());
            return null;
        }
    }
}


