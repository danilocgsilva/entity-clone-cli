<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use PDO;

class CreateDatabaseDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        try {
            // Get connection ID
            $connectionId = (int) $this->requireOption('connection-id', 'Please enter the database connection ID:');
            if (!$connectionId) {
                return null;
            }

            // Validate connection exists
            $repository = $this->entityManager->getRepository(DatabaseAccess::class);
            $databaseAccess = $repository->find($connectionId);
            if (!$databaseAccess) {
                $this->io->error("Database connection with ID {$connectionId} not found.");
                return null;
            }

            // Get database name
            $databaseName = $this->requireOption('database-name', 'Please enter the name of the new database to create:');
            if (!$databaseName) {
                return null;
            }

            return [
                'connectionId' => $connectionId,
                'databaseAccess' => $databaseAccess,
                'databaseName' => $databaseName
            ];
        } catch (\Exception $e) {
            $this->io->error('Error collecting create database data: ' . $e->getMessage());
            return null;
        }
    }
}

