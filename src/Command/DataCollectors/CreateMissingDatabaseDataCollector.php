<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use PDO;

class CreateMissingDatabaseDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        try {
            // Resolve connections using existing logic
            $repository = $this->entityManager->getRepository(DatabaseAccess::class);
            $connections = $repository->findAll();
            
            if (empty($connections)) {
                $this->io->error('No connections registered.');
                return null;
            }

            $id1 = (int) $this->input->getOption('connection-id-1');
            $id2 = (int) $this->input->getOption('connection-id-2');

            if (!$id1 || !$id2) {
                $this->io->table(['ID', 'Name'], array_map(fn($c) => [$c->getId(), $c->getName()], $connections));
            }

            if (!$id1) {
                $id1 = (int) $this->io->ask('Please enter the first connection ID:');
                if (!$id1) { 
                    $this->io->error('First connection ID is required.'); 
                    return null; 
                }
            }

            if (!$id2) {
                $id2 = (int) $this->io->ask('Please enter the second connection ID:');
                if (!$id2) { 
                    $this->io->error('Second connection ID is required.'); 
                    return null; 
                }
            }

            $access1 = $repository->find($id1);
            if (!$access1) { 
                $this->io->error("Connection ID {$id1} not found."); 
                return null; 
            }

            $access2 = $repository->find($id2);
            if (!$access2) { 
                $this->io->error("Connection ID {$id2} not found."); 
                return null; 
            }

            return [
                'access1' => $access1,
                'access2' => $access2
            ];
        } catch (\Exception $e) {
            $this->io->error('Error collecting missing database data: ' . $e->getMessage());
            return null;
        }
    }
}
