<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;

#[AsCommand(
    name: 'app:list-connections',
    description: 'List all registered database connections.'
)]
class ListConnectionsCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Connections List');

        try {
            $entityManager = $this->createEntityManager();

            $databaseAccesses = $entityManager->getRepository(DatabaseAccess::class)->findAll();

            if (empty($databaseAccesses)) {
                $io->info('No database connections registered.');
                return Command::SUCCESS;
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

        } catch (\Exception $e) {
            $io->error('Error retrieving connections: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
