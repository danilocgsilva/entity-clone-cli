<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

abstract class BaseCommand extends Command
{
    protected function createEntityManager(): EntityManagerInterface
    {
        return EntityManagerFactory::create(
            projectRoot: __DIR__ . '/..',
            entityPaths: [__DIR__ . '/../src/Entities'],
        );
    }

    /**
     * Returns the option value if set, otherwise asks interactively.
     * Returns null and outputs an error if the value is empty after asking.
     */
    protected function requireOption(InputInterface $input, SymfonyStyle $io, string $option, string $question): ?string
    {
        $value = $input->getOption($option);
        if (!$value) {
            $value = $io->ask($question);
        }
        if (!$value) {
            $io->error(ucfirst(str_replace('-', ' ', $option)) . ' is required.');
            return null;
        }
        return $value;
    }
}
