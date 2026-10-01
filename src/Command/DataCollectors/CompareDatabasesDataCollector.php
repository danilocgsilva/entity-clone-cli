<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use PDO;

class CompareDatabasesDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        try {
            $connectionId1 = (int) $this->input->getOption('connection-id-1');
            $connectionId2 = (int) $this->input->getOption('connection-id-2');

            if (!$connectionId1 || !$connectionId2) {
                $repository = $this->entityManager->getRepository(DatabaseAccess::class);
                $this->listConnections($repository->findAll());
            }

            if (!$connectionId1) {
                $connectionId1 = (int) $this->io->ask('Please enter the first connection ID:');
                if (!$connectionId1) {
                    $this->io->error('First connection ID is required.');
                    return null;
                }
            }

            if (!$connectionId2) {
                $connectionId2 = (int) $this->io->ask('Please enter the second connection ID:');
                if (!$connectionId2) {
                    $this->io->error('Second connection ID is required.');
                    return null;
                }
            }

            $repository = $this->entityManager->getRepository(DatabaseAccess::class);
            $access1 = $repository->find($connectionId1);
            if (!$access1) {
                $this->io->error("Connection ID {$connectionId1} not found.");
                return null;
            }

            $access2 = $repository->find($connectionId2);
            if (!$access2) {
                $this->io->error("Connection ID {$connectionId2} not found.");
                return null;
            }

            return [
                'connectionId1' => $connectionId1,
                'connectionId2' => $connectionId2,
                'access1' => $access1,
                'access2' => $access2
            ];
        } catch (\Exception $e) {
            $this->io->error('Error collecting database comparison data: ' . $e->getMessage());
            return null;
        }
    }

    private function listConnections(array $connections): void
    {
        $this->io->table(
            ['ID', 'Name'],
            array_map(fn($c) => [$c->getId(), $c->getName()], $connections)
        );
    }
}

