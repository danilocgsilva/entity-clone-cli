<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;

class TestAllConnectionsDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        try {
            $entityManager = Helpers::createEntityManager();
            $repository = $entityManager->getRepository(DatabaseAccess::class);
            $databaseAccesses = $repository->findAll();
            
            if (empty($databaseAccesses)) {
                $this->io->warning('No database connections found.');
                return [];
            }
            
            return ['databaseAccesses' => $databaseAccesses];
        } catch (\Exception $e) {
            $this->io->error('Error collecting database connections: ' . $e->getMessage());
            return null;
        }
    }
}
