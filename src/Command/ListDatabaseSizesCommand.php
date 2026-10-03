<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityClone\Domain;
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\ListDatabaseSizesDataCollector;
use Exception;

#[AsCommand(
    name: 'anatomy:list-database-sizes',
    description: 'Show the size of all databases for a connection.'
)]
class ListDatabaseSizesCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'connection-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Database connection ID to list sizes (optional - will prompt if not provided)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Sizes');

        try {
            $dataCollector = new ListDatabaseSizesDataCollector($input, $io);
            $data = $dataCollector->collect();
            
            if (!$data) {
                return Command::FAILURE;
            }

            $connectionId = $data['connectionId'];
            $access = $data['access'];

            $pdo = Domain::getPdoFromDatabaseAccessId($connectionId, $this->entityManager);

            $io->section("Databases for '{$access->getName()}' (ID: {$connectionId})");

            $sizeCount = 0;
            
            foreach (Domain::listDatabaseSizes($pdo) as $sizeInfo) {
                $size = $sizeInfo['size'];

                if ($size < 1024) {
                    $sizeFormatted = round($size, 2) . ' bytes';
                } elseif ($size < 1024 * 1024) {
                    $sizeFormatted = round($size / 1024, 2) . ' KB';
                } elseif ($size < 1024 * 1024 * 1024) {
                    $sizeFormatted = round($size / (1024 * 1024), 2) . ' MB';
                } else {
                    $sizeFormatted = round($size / (1024 * 1024 * 1024), 2) . ' GB';
                }

                $io->text([
                    "- {$sizeInfo['database']}: {$sizeFormatted}"
                ]);

                $sizeCount++;
            }

            if ($sizeCount === 0) {
                $io->warning('No databases found on this connection.');
            } else {
                $io->section('Summary');
                $io->text([
                    "Total databases: {$sizeCount}",
                    "Connection: '{$access->getName()}' (ID: {$connectionId})"
                ]);
            }

        } catch (Exception $e) {
            $io->error("Error listing database sizes: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}