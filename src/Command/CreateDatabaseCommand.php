<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityCloneCli\Helpers;
use Exception;
use PDOException;

#[AsCommand(
    name: 'app:create-database',
    description: 'Create a new empty database using an existing connection.'
)]
class CreateDatabaseCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'connection-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Database connection ID to use for creating the new database'
            )
            ->addOption(
                'database-name',
                'n',
                InputOption::VALUE_REQUIRED,
                'Name of the new database to create'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create New Database');

        try {
            $entityManager = Helpers::createEntityManager();
            $repository = $entityManager->getRepository(DatabaseAccess::class);

            // Get connection ID
            $connectionId = (int) $this->requireOption($input, $io, 'connection-id', 'Please enter the database connection ID:');
            if (!$connectionId) {
                return Command::FAILURE;
            }

            // Validate connection exists
            $databaseAccess = $repository->find($connectionId);
            if (!$databaseAccess) {
                $io->error("Database connection with ID {$connectionId} not found.");
                return Command::FAILURE;
            }

            // Get database name
            $databaseName = $this->requireOption($input, $io, 'database-name', 'Please enter the name of the new database to create:');
            if (!$databaseName) {
                return Command::FAILURE;
            }

            // Create PDO connection using the existing connection details
            $dsn = "mysql:host={$databaseAccess->getHost()};port={$databaseAccess->getPort()};charset=utf8mb4";
            $pdo = new \PDO(
                $dsn,
                $databaseAccess->getUser(),
                $databaseAccess->getPassword(),
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );

            // Check if database already exists
            $stmt = $pdo->prepare('SHOW DATABASES LIKE ?');
            $stmt->execute([$databaseName]);
            
            if ($stmt->fetch()) {
                $io->error("Database '{$databaseName}' already exists.");
                return Command::FAILURE;
            }

            // Create the new database
            $pdo->exec("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            $io->success("Database '{$databaseName}' created successfully using connection '{$databaseAccess->getName()}' (ID: {$connectionId}).");
            return Command::SUCCESS;

        } catch (PDOException $e) {
            $io->error('Database creation failed: ' . $e->getMessage());
            return Command::FAILURE;
        } catch (Exception $e) {
            $io->error('Error creating database: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
