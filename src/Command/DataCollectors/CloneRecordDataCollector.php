<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use PDO;

class CloneRecordDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        try {
            $sourceDatabaseName = $this->requireOption('source-database-name', 'Enter source database name');
            if (!$sourceDatabaseName) return null;

            $targetDatabaseName = $this->requireOption('target-database-name', 'Enter target database name');
            if (!$targetDatabaseName) return null;

            $tableName = $this->requireOption('table-name', 'Enter table name to clone record from');
            if (!$tableName) return null;

            $recordId = (int) $this->requireOption('record-id', 'Enter ID of the record to clone');
            if (!$recordId) return null;

            // Resolve connection IDs
            $sourceConnectionId = $this->getConnectionIdFromDatabaseName($sourceDatabaseName);
            $targetConnectionId = $this->getConnectionIdFromDatabaseName($targetDatabaseName);
            
            if (!$sourceConnectionId || !$targetConnectionId) {
                $this->io->error('Could not find connection IDs for the specified databases');
                return null;
            }

            return [
                'sourceDatabaseName' => $sourceDatabaseName,
                'targetDatabaseName' => $targetDatabaseName,
                'tableName' => $tableName,
                'recordId' => $recordId,
                'sourceConnectionId' => $sourceConnectionId,
                'targetConnectionId' => $targetConnectionId
            ];
        } catch (\Exception $e) {
            $this->io->error('Error collecting clone record data: ' . $e->getMessage());
            return null;
        }
    }

    private function getConnectionIdFromDatabaseName(string $databaseName): ?int
    {
        $repository = $this->entityManager->getRepository(DatabaseAccess::class);
        $databaseAccesses = $repository->findAll();
        
        foreach ($databaseAccesses as $dbAccess) {
            try {
                $pdo = \Danilocgsilva\EntityClone\Domain::createPdoFromDatabaseConnectionEntity($dbAccess);
                $databases = \Danilocgsilva\EntityClone\Domain::listDatabases($pdo, true);
                
                if (in_array($databaseName, $databases)) {
                    return $dbAccess->getId();
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return null;
    }
}
