<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\ListConnectionsDataCollector;

#[AsCommand(
    name: 'app:list-connections',
    description: 'List all registered database connections.'
)]
class ListConnectionsCommand extends BaseCommand
{
    private DatabaseConnectionLister $connectionLister;

    public function __construct(DatabaseConnectionLister $connectionLister)
    {
        $this->connectionLister = $connectionLister;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Connections List');

        try {
            $dataCollector = new ListConnectionsDataCollector($input, $io);
            $data = $dataCollector->collect();
            
            $this->connectionLister->listConnections($io);
            
        } catch (\Exception $e) {
            $io->error('Error retrieving connections: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
