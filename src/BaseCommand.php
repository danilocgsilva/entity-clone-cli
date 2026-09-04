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
}
