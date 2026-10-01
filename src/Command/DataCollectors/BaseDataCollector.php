<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use PDO;

abstract class BaseDataCollector
{
    protected InputInterface $input;
    protected SymfonyStyle $io;
    protected EntityManagerInterface $entityManager;

    public function __construct(InputInterface $input, SymfonyStyle $io)
    {
        $this->input = $input;
        $this->io = $io;
        $this->entityManager = Helpers::createEntityManager();
    }

    protected function requireOption(string $option, string $question): ?string
    {
        $value = $this->input->getOption($option);
        if (!$value) {
            $value = $this->io->ask($question);
        }
        if (!$value) {
            $this->io->error(ucfirst(str_replace('-', ' ', $option)) . ' is required.');
            return null;
        }
        return $value;
    }

    protected function requireOptionByNumber(string $option, string $question, array $options): ?string
    {
        $value = $this->input->getOption($option);
        if ($value) {
            if (!in_array($value, $options)) {
                $this->io->error("Provided value '{$value}' is not in the available options.");
                return null;
            }
            return $value;
        }

        if (empty($options)) {
            $this->io->error('No options available for selection.');
            return null;
        }

        foreach ($options as $index => $optionValue) {
            $this->io->writeln(sprintf('%d. %s', $index + 1, $optionValue));
        }

        $pick = (int) $this->io->ask($question);
        if ($pick < 1 || $pick > count($options)) {
            $this->io->error('Invalid selection.');
            return null;
        }

        return $options[$pick - 1];
    }
}
