<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use PDO;

class ListTableEntryCountsDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        try {
            $connectionId = (int) $this->input->getOption('connection-id');

            if (!$connectionId) {
                $repository = $this->entityManager->getRepository(DatabaseAccess::class);
                $connections = $repository->findAll();
                
                if (empty($connections)) {
                    $this->io->error('No connections registered.');
                    return null;
                }

                $this->io->table(
                    ['ID', 'Name'],
                    array_map(fn($c) => [$c->getId(), $c->getName()], $connections)
                );
                
                $connectionId = (int) $this->io->ask('Please enter the database connection ID:');
                if (!$connectionId) {
                    $this->io->error('Connection ID is required.');
                    return null;
                }
            }

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
            $this->io->error('Error collecting table entry counts data: ' . $e->getMessage());
            return null;
        }
    }
}
