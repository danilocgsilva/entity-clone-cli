<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Domain;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityClone\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:list-fields',
    description: 'List all fields from a database table.'
)]
class ListFieldsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'connection-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Database connection ID'
            )
            ->addOption(
                'table-name',
                't',
                InputOption::VALUE_REQUIRED,
                'Table name to list fields from'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Table Fields List');

        try {
            // Get connection ID and table name from input
            $connectionId = (int) $input->getOption('connection-id');
            $tableName = $input->getOption('table-name');

            if (!$connectionId) {
                $io->error('Connection ID is required. Use --connection-id or -c option.');
                return Command::FAILURE;
            }

            if (!$tableName) {
                $io->error('Table name is required. Use --table-name or -t option.');
                return Command::FAILURE;
            }

            // Create EntityManager
            $entityManager = EntityManagerFactory::create(
                projectRoot: __DIR__ . '/..',
                entityPaths: [__DIR__ . '/../src/Entities'],
            );

            // Get PDO connection from database access ID
            $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $entityManager);

            // Get fields from table
            $fields = Domain::getFieldsFromTable($pdo, $tableName);

            if (empty($fields)) {
                $io->info("No fields found for table '{$tableName}' in connection {$connectionId}.");
                return Command::SUCCESS;
            }

            // Prepare table data
            $tableRows = [];
            foreach ($fields as $field) {
                $tableRows[] = [
                    $field->name,
                    $field->type,
                    $field->null ? 'YES' : 'NO',
                    $field->default ?? 'NULL',
                    $field->extra,
                    $field->comment,
                ];
            }

            $io->table(
                ['Field', 'Type', 'Null', 'Default', 'Extra', 'Comment'],
                $tableRows
            );

        } catch (\Exception $e) {
            $io->error('Error retrieving table fields: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}