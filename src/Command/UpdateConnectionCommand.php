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
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\UpdateConnectionDataCollector;
use Exception;

#[AsCommand(
    name: 'app:update-connection',
    description: 'Update an existing database connection.'
)]
class UpdateConnectionCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'connection-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Database connection ID to update'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Update Database Connection');

        try {
            $dataCollector = new UpdateConnectionDataCollector($input, $io);
            $data = $dataCollector->collect();
            
            if (!$data) {
                return Command::FAILURE;
            }

            $connectionId = $data['connectionId'];
            $entityManager = Helpers::createEntityManager();

            $databaseAccess = $entityManager->getRepository(DatabaseAccess::class)->find($connectionId);
            
            if (!$databaseAccess) {
                $io->error("Database connection with ID {$connectionId} not found.");
                return Command::FAILURE;
            }

            $databaseAccess
                ->setName($data['name'])
                ->setHost($data['host'])
                ->setUser($data['user'])
                ->setPassword($data['password'])
                ->setDatabaseName($data['databaseName'])
                ->setPort($data['port']);

            $entityManager->flush();

            $io->success("Database connection '{$data['name']}' (ID: {$connectionId}) updated successfully.");
            return Command::SUCCESS;

        } catch (Exception $e) {
            $io->error('Error updating database connection: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}