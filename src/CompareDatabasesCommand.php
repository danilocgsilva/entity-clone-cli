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
use Danilocgsilva\EntityClone\DatabaseWorks;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityClone\EntityManagerFactory;

#[AsCommand(
    name: 'app:compare-databases',
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

        $helper = $this->getHelper('question');

        try {
            $entityManager = EntityManagerFactory::create(
                projectRoot: __DIR__ . '/..',
                entityPaths: [__DIR__ . '/../src/Entities'],
            );

            $repository = $entityManager->getRepository(DatabaseAccess::class);

            $connectionId1 = (int) $input->getOption('connection-id-1');
            $connectionId2 = (int) $input->getOption('connection-id-2');

            if (!$connectionId1 || !$connectionId2) {
                $this->listConnections($io, $repository->findAll());
            }

            if (!$connectionId1) {
                $connectionId1 = (int) $helper->ask($input, $output, new Question('Please enter the first connection ID: '));
                if (!$connectionId1) {
                    $io->error('First connection ID is required.');
                    return Command::FAILURE;
                }
            }

            if (!$connectionId2) {
                $connectionId2 = (int) $helper->ask($input, $output, new Question('Please enter the second connection ID: '));
                if (!$connectionId2) {
                    $io->error('Second connection ID is required.');
                    return Command::FAILURE;
                }
            }

            $access1 = $repository->find($connectionId1);
            if (!$access1) {
                $io->error("Connection ID {$connectionId1} not found.");
                return Command::FAILURE;
            }

            $access2 = $repository->find($connectionId2);
            if (!$access2) {
                $io->error("Connection ID {$connectionId2} not found.");
                return Command::FAILURE;
            }

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

    private function listConnections(SymfonyStyle $io, array $connections): void
    {
        $io->table(
            ['ID', 'Name'],
            array_map(fn($c) => [$c->getId(), $c->getName()], $connections)
        );
    }
}
