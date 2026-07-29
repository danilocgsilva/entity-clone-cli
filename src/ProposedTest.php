<?php

namespace Danilocgsilva\EntityCloneCli;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\EntityManagerFactory;

#[AsCommand(
    name: 'app:test'
)]
class ProposedTest extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $em = EntityManagerFactory::create(
            projectRoot: __DIR__,
            entityPaths: [__DIR__ . '/vendor/danilocgsilva/entity-clone/src/Entities'],
        );

        var_dump($em->getConnection()->isConnected() ? 'connected' : 'not connected');

        $io->success("Hello, just a test!");

        return Command::SUCCESS;
    }
}