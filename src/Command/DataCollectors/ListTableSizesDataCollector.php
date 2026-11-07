<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Danilocgsilva\EntityClone\Domain;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use PDO;

class ListTableSizesDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        try {
            $repository = $this->entityManager->getRepository(DatabaseAccess::class);

            $connectionId = (int) $this->requireOption('connection-id', 'Please enter the database connection ID:');
            if (!$connectionId) {
                return null;
            }

            $databaseAccess = $repository->find($connectionId);
            if (!$databaseAccess) {
                $this->io->error("Database connection with ID {$connectionId} not found.");
                return null;
            }

            $pdo = Domain::createPdoFromDatabaseConnectionEntity($databaseAccess);

            $stmt = $pdo->prepare('SHOW DATABASES');
            $stmt->execute();
            $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($databases)) {
                $this->io->error('No databases found in the connection.');
                return null;
            }

            $databaseName = $this->requireOptionByNumber('database-name', 'Enter the number of the database you want to list tables from:', $databases);
            if (!$databaseName) {
                return null;
            }

            return ['pdo' => $pdo, 'databaseName' => $databaseName];
        } catch (\Exception $e) {
            $this->io->error('Error collecting data: ' . $e->getMessage());
            return null;
        }
    }
}
