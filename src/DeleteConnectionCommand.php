<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;

#[AsCommand(
    name: 'app:delete-connection',
    description: 'Delete a registered database connection.'
)]
class DeleteConnectionCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'connection-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Database connection ID to delete'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Delete Database Connection');

        try {
            $connectionId = (int) $this->requireOption($input, $io, 'connection-id', 'Please enter the database connection ID to delete:');
            if (!$connectionId) return Command::FAILURE;

            $entityManager = $this->createEntityManager();

            // Find the connection
            $databaseAccess = $entityManager->getRepository(DatabaseAccess::class)->find($connectionId);
            
            if (!$databaseAccess) {
                $io->error("Database connection with ID {$connectionId} not found.");
                return Command::FAILURE;
            }

            // Confirm deletion
            $question = new ConfirmationQuestion(
                "Are you sure you want to delete the connection '{$databaseAccess->getName()}' (ID: {$connectionId})? (yes/no) ",
                false
            );
            
            /** @var \Symfony\Component\Console\Helper\QuestionHelper */
            $helper = $this->getHelper('question');
            
            if (!$helper->ask($input, $output, $question)) {
                $io->info('Deletion cancelled.');
                return Command::SUCCESS;
            }

            // Delete the connection
            $entityManager->remove($databaseAccess);
            $entityManager->flush();

            $io->success("Database connection '{$databaseAccess->getName()}' (ID: {$connectionId}) deleted successfully.");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error deleting database connection: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
