<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Domain;
use Danilocgsilva\EntityClone\EntityManagerFactory;
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\ListFieldsDataCollector;
use Exception;

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
            $dataCollector = new ListFieldsDataCollector($input, $io);
            $data = $dataCollector->collect();
            
            if (!$data) {
                return Command::FAILURE;
            }

            $connectionId = $data['connectionId'];
            $tableName = $data['tableName'];
            $access = $data['access'];

            $entityManager = EntityManagerFactory::create(
                projectRoot: __DIR__ . '/..',
                entityPaths: [__DIR__ . '/../src/Entities'],
            );

            $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $entityManager);

            $fields = Domain::getFieldsFromTable($pdo, $tableName);

            if (empty($fields)) {
                $io->info("No fields found for table '{$tableName}' in connection {$connectionId}.");
                return Command::SUCCESS;
            }

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

        } catch (Exception $e) {
            $io->error('Error retrieving table fields: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}