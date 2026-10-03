<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use PDO;

class DeleteConnectionDataCollector extends BaseDataCollector
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
            $this->connectionLister->listConnections($this->io);
            
            $connectionId = (int) $this->requireOption('connection-id', 'Please enter the database connection ID to delete:');
            if (!$connectionId) return null;

            $repository = $this->entityManager->getRepository(DatabaseAccess::class);
            $databaseAccess = $repository->find($connectionId);
            
            if (!$databaseAccess) {
                $this->io->error("Database connection with ID {$connectionId} not found.");
                return null;
            }

            return [
                'connectionId' => $connectionId,
                'databaseAccess' => $databaseAccess
            ];
        } catch (\Exception $e) {
            $this->io->error('Error collecting delete connection data: ' . $e->getMessage());
            return null;
        }
    }
}