<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\EntityManagerFactory;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;

abstract class BaseCommand extends Command
{
    protected function createEntityManager(): EntityManagerInterface
    {
        return EntityManagerFactory::create(
            projectRoot: __DIR__ . '/..',
            entityPaths: [__DIR__ . '/../src/Entities'],
        );
    }

    protected function requireOption(InputInterface $input, SymfonyStyle $io, string $option, string $question): ?string
    {
        $value = $input->getOption($option);
        if (!$value) {
            $value = $io->ask($question);
        }
        if (!$value) {
            $io->error(ucfirst(str_replace('-', ' ', $option)) . ' is required.');
            return null;
        }
        return $value;
    }

    protected function askDatabaseName(InputInterface $input, SymfonyStyle $io, \PDO $pdo): ?string
    {
        $value = $input->getOption('database-name');
        if ($value) {
            return $value;
        }

        $stmt = $pdo->query('SHOW DATABASES');
        $databases = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($databases)) {
            $io->error('No databases found for this connection.');
            return null;
        }

        sort($databases);
        foreach ($databases as $i => $db) {
            $io->writeln(sprintf('%d. %s', $i + 1, $db));
        }

        $pick = (int) $io->ask('Pick a database number');
        if ($pick < 1 || $pick > count($databases)) {
            $io->error('Invalid selection.');
            return null;
        }

        return $databases[$pick - 1];
    }

    protected function askTableName(InputInterface $input, SymfonyStyle $io, \PDO $pdo, string $databaseName): ?string
    {
        $value = $input->getOption('table-name');
        if ($value) {
            return $value;
        }

        $tables = \Danilocgsilva\EntityClone\Domain::listTables($pdo, $databaseName);
        if (empty($tables)) {
            $io->error('No tables found in this database.');
            return null;
        }

        sort($tables);
        foreach ($tables as $i => $table) {
            $io->writeln(sprintf('%d. %s', $i + 1, $table));
        }

        $pick = (int) $io->ask('Pick a table number:');
        if ($pick < 1 || $pick > count($tables)) {
            $io->error('Invalid selection.');
            return null;
        }

        return $tables[$pick - 1];
    }

    protected function askConnectionId(InputInterface $input, SymfonyStyle $io, EntityManagerInterface $entityManager): ?int
    {
        $value = $input->getOption('connection-id');
        if ($value) {
            return (int) $value;
        }

        $connections = $entityManager->getRepository(DatabaseAccess::class)->findAll();
        if (empty($connections)) {
            $io->error('No connections registered.');
            return null;
        }

        $io->table(['ID', 'Name'], array_map(fn($c) => [$c->getId(), $c->getName()], $connections));
        $id = $io->ask('Please enter the connection ID:');
        if (!$id) {
            $io->error('Connection ID is required.');
            return null;
        }
        return (int) $id;
    }

    /**
     * Resolves two DatabaseAccess entities from connection-id-1 / connection-id-2 options,
     * prompting the user interactively when not provided.
     *
     * @return DatabaseAccess[]|null  [access1, access2] or null on failure
     */
    protected function resolveTwoConnections(InputInterface $input, SymfonyStyle $io): ?array
    {
        $entityManager = $this->createEntityManager();
        $repository    = $entityManager->getRepository(DatabaseAccess::class);

        $id1 = (int) $input->getOption('connection-id-1');
        $id2 = (int) $input->getOption('connection-id-2');

        if (!$id1 || !$id2) {
            $io->table(['ID', 'Name'], array_map(fn($c) => [$c->getId(), $c->getName()], $repository->findAll()));
        }

        if (!$id1) {
            $id1 = (int) $io->ask('Please enter the first connection ID:');
            if (!$id1) { $io->error('First connection ID is required.'); return null; }
        }

        if (!$id2) {
            $id2 = (int) $io->ask('Please enter the second connection ID:');
            if (!$id2) { $io->error('Second connection ID is required.'); return null; }
        }

        $access1 = $repository->find($id1);
        if (!$access1) { $io->error("Connection ID {$id1} not found."); return null; }

        $access2 = $repository->find($id2);
        if (!$access2) { $io->error("Connection ID {$id2} not found."); return null; }

        return [$access1, $access2];
    }
}
