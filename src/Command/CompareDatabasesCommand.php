<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\DatabaseWorks;
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\CompareDatabasesDataCollector;

#[AsCommand(
    name: 'anatomy:compare-databases',
    description: 'Compare the list of databases between two connections.'
)]
class CompareDatabasesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('connection-id-1', null, InputOption::VALUE_REQUIRED, 'First database connection ID')
            ->addOption('connection-id-2', null, InputOption::VALUE_REQUIRED, 'Second database connection ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Databases Comparison');

        try {
            $dataCollector = new CompareDatabasesDataCollector($input, $io);
            $data = $dataCollector->collect();
            
            if (!$data) {
                return Command::FAILURE;
            }

            $connectionId1 = $data['connectionId1'];
            $connectionId2 = $data['connectionId2'];
            $access1 = $data['access1'];
            $access2 = $data['access2'];

            $databases1 = (new DatabaseWorks($access1))->listDatabasesNames();
            $databases2 = (new DatabaseWorks($access2))->listDatabasesNames();

            $onlyInFirst  = array_values(array_diff($databases1, $databases2));
            $onlyInSecond = array_values(array_diff($databases2, $databases1));
            $common       = array_values(array_intersect($databases1, $databases2));

            if (!empty($onlyInFirst)) {
                $io->section("Only in connection {$connectionId1} ({$access1->getName()}):");
                $io->listing($onlyInFirst);
            }

            if (!empty($onlyInSecond)) {
                $io->section("Only in connection {$connectionId2} ({$access2->getName()}):");
                $io->listing($onlyInSecond);
            }

            if (!empty($common)) {
                $io->section('Common databases:');
                $io->listing($common);
            }

            if (empty($onlyInFirst) && empty($onlyInSecond)) {
                $io->success('Both connections have identical database lists.');
            }

        } catch (\Exception $e) {
            $io->error('Error comparing databases: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
