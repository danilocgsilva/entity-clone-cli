<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use PDO;

class GetCreateTableStatementDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        try {
            $connectionId = (int) $this->requireOption('connection-id', 'Enter database connection ID:');
            if (!$connectionId) return null;

            $databaseName = $this->requireOption('database-name', 'Enter database name:');
            if (!$databaseName) return null;

            $tableName = $this->requireOption('table-name', 'Enter table name:');
            if (!$tableName) return null;

            // Validate connection exists
            $repository = $this->entityManager->getRepository(DatabaseAccess::class);
            $databaseAccess = $repository->find($connectionId);
            
            if (!$databaseAccess) {
                $this->io->error("Database connection with ID {$connectionId} not found.");
                return null;
            }

            return [
                'connectionId' => $connectionId,
                'databaseName' => $databaseName,
                'tableName' => $tableName,
                'databaseAccess' => $databaseAccess
            ];
        } catch (\Exception $e) {
            $this->io->error('Error collecting create table statement data: ' . $e->getMessage());
            return null;
        }
    }

    private function askConnectionId(InputInterface $input, SymfonyStyle $io, EntityManagerInterface $entityManager): ?int
    {
        $connectionId = (int) $input->getOption('connection-id');
        
        if (!$connectionId) {
            $this->listConnections($entityManager);
            $connectionId = (int) $io->ask('Please enter the database connection ID:');
        }
        
        if (!$connectionId) {
            $io->error('Connection ID is required.');
            return null;
        }
        
        return $connectionId;
    }

    private function askDatabaseName(InputInterface $input, SymfonyStyle $io, PDO $pdo): ?string
    {
        $databaseName = $input->getOption('database-name');
        
        if (!$databaseName) {
            $databases = \Danilocgsilva\EntityClone\Domain::listDatabases($pdo);
            if (empty($databases)) {
                $io->error('No databases found.');
                return null;
            }
            
            $this->io->listing($databases);
            $databaseName = $io->ask('Please enter the database name:');
        }
        
        if (!$databaseName) {
            $io->error('Database name is required.');
            return null;
        }
        
        return $databaseName;
    }

    private function askTableName(InputInterface $input, SymfonyStyle $io, PDO $pdo, string $databaseName): ?string
    {
        $tableName = $input->getOption('table-name');
        
        if (!$tableName) {
            $tables = \Danilocgsilva\EntityClone\Domain::listTables($pdo, $databaseName);
            if (empty($tables)) {
                $io->error('No tables found in the specified database.');
                return null;
            }
            
            $this->io->listing($tables);
            $tableName = $io->ask('Please enter the table name:');
        }
        
        if (!$tableName) {
            $io->error('Table name is required.');
            return null;
        }
        
        return $tableName;
    }

    private function listConnections(EntityManagerInterface $entityManager): void
    {
        $repository = $entityManager->getRepository(DatabaseAccess::class);
        $connections = $repository->findAll();
        
        if (empty($connections)) {
            $this->io->error('No connections registered.');
            return;
        }
        
        $this->io->table(
            ['ID', 'Name'],
            array_map(fn($c) => [$c->getId(), $c->getName()], $connections)
        );
    }
}
