<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Danilocgsilva\EntityClone\Helpers;
use Danilocgsilva\EntityClone\Domain;
use Danilocgsilva\EntityClone\EntityManagerFactory;

#[AsCommand(
    name: 'db:table:create-from-source',
    description: 'Create a table in target database from source database structure'
)]
class CreateTableFromSourceCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('source-connection', 's', InputOption::VALUE_OPTIONAL, 'Source connection ID')
            ->addOption('target-connection', 't', InputOption::VALUE_OPTIONAL, 'Target connection ID')
            ->addOption('database-name', 'd', InputOption::VALUE_OPTIONAL, 'Database name')
            ->addOption('table-name', null, InputOption::VALUE_OPTIONAL, 'Table name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create Table from Source Database');

        // Get or prompt for source connection ID
        $sourceConnectionId = $this->getOptionOrPrompt($input, $io, 'source-connection', 'Enter source connection ID:');
        
        // Get or prompt for target connection ID
        $targetConnectionId = $this->getOptionOrPrompt($input, $io, 'target-connection', 'Enter target connection ID:');
        
        // Get or prompt for database name
        $databaseName = $this->getOptionOrPrompt($input, $io, 'database-name', 'Enter database name:');
        
        // Get or prompt for table name
        $tableName = $this->getOptionOrPrompt($input, $io, 'table-name', 'Enter table name:');

        try {
            $entityManager = EntityManagerFactory::create(
                projectRoot: __DIR__ . '/..',
                entityPaths: [__DIR__ . '/../src/Entities'],
            );

            // Validate connections exist
            $sourceConnection = $entityManager->getRepository(\Danilocgsilva\EntityClone\Entities\DatabaseAccess::class)
                ->find($sourceConnectionId);
            
            if (!$sourceConnection) {
                throw new \RuntimeException("Source connection with ID {$sourceConnectionId} not found");
            }

            $targetConnection = $entityManager->getRepository(\Danilocgsilva\EntityClone\Entities\DatabaseAccess::class)
                ->find($targetConnectionId);
            
            if (!$targetConnection) {
                throw new \RuntimeException("Target connection with ID {$targetConnectionId} not found");
            }

            // Create the table
            Domain::createTableFromSource(
                $sourceConnectionId,
                $targetConnectionId,
                $databaseName,
                $tableName,
                $entityManager
            );

            $io->success("Table '{$tableName}' successfully created in database '{$databaseName}' from source connection");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Error creating table: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getOptionOrPrompt(InputInterface $input, SymfonyStyle $io, string $optionName, string $prompt): ?string
    {
        $value = $input->getOption($optionName);
        
        if (empty($value)) {
            $value = $io->ask($prompt);
        }
        
        return $value;
    }
}
