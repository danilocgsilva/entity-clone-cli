<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;
use Danilocgsilva\EntityCloneCli\Helpers;
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\DeleteConnectionDataCollector;

#[AsCommand(
    name: 'app:delete-connection',
    description: 'Delete a registered database connection.'
)]
class DeleteConnectionCommand extends BaseCommand
{
    private DatabaseConnectionLister $connectionLister;

    public function __construct(DatabaseConnectionLister $connectionLister)
    {
        $this->connectionLister = $connectionLister;
        parent::__construct();
    }

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
            $dataCollector = new DeleteConnectionDataCollector($input, $io, $this->connectionLister);
            $data = $dataCollector->collect();
            
            if (!$data) {
                return Command::FAILURE;
            }

            $connectionId = $data['connectionId'];
            $databaseAccess = $data['databaseAccess'];

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

            $this->entityManager->remove($databaseAccess);
            $this->entityManager->flush();

            $io->success("Database connection '{$databaseAccess->getName()}' (ID: {$connectionId}) deleted successfully.");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error deleting database connection: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
