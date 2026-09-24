<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;

class DatabaseConnectionLister
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function listConnections(SymfonyStyle $io): void
    {
        $databaseAccesses = $this->entityManager->getRepository(DatabaseAccess::class)->findAll();

        if (empty($databaseAccesses)) {
            $io->info('No database connections registered.');
            return;
        }

        $tableRows = [];
        foreach ($databaseAccesses as $dbAccess) {
            $tableRows[] = [
                $dbAccess->getId(),
                $dbAccess->getName(),
                $dbAccess->getHost(),
                $dbAccess->getUser(),
                $dbAccess->getDatabaseName(),
                $dbAccess->getPort(),
            ];
        }

        $io->table(
            ['ID', 'Name', 'Host', 'User', 'Database', 'Port'],
            $tableRows
        );
    }
}
