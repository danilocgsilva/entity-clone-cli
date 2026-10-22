<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use PDO;
use Danilocgsilva\EntityClone\Domain;
use Exception;

class ShowForeignKeysDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        try {
            $connectionId = $this->requireOption('connection-id', 'Enter connection ID:');
            if (!$connectionId) {
                return null;
            }

            $entityManager = Helpers::createEntityManager();
            $pdo = Domain::getPdoFromDatabaseAccessId((int) $connectionId, $entityManager);
            
            $stmt = $pdo->query('SHOW DATABASES');
            $databases = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (empty($databases)) {
                $this->io->error('No databases found for this connection.');
                return null;
            }
            
            sort($databases);
            
            $databaseName = $this->requireOptionByNumber(
                'database-name', 
                'Pick a database number:', 
                $databases
            );
            if (!$databaseName) {
                return null;
            }

            $tableName = $this->requireOption('table-name', 'Enter table name:');
            if (!$tableName) {
                return null;
            }

            return [
                'connectionId' => (int) $connectionId,
                'databaseName' => $databaseName,
                'tableName' => $tableName
            ];
        } catch (Exception $e) {
            $this->io->error('Error collecting foreign keys data: ' . $e->getMessage());
            return null;
        }
    }
}
