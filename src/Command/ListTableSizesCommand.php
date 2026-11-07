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
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\ListTableSizesDataCollector;
use Exception;

#[AsCommand(
    name: 'anatomy:list-table-sizes',
    description: 'List all tables and their sizes from a database connection.'
)]
class ListTableSizesCommand extends BaseCommand
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
            ->addOption('connection-id', 'c', InputOption::VALUE_REQUIRED, 'Database connection ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Table Sizes List');

        try {
            $this->connectionLister->listConnections($io);

            $dataCollector = new ListTableSizesDataCollector($input, $io, $this->createEntityManager());
            $data = $dataCollector->collect();

            if (!$data) {
                return Command::FAILURE;
            }

            $tableSizes = [];
            foreach (Domain::listTableSizes($data['pdo'], $data['databaseName']) as $tableSize) {
                $tableSizes[] = [
                    'Table' => $tableSize['table'],
                    'Size'  => $this->formatFileSize((float) $tableSize['size'])
                ];
            }

            if (empty($tableSizes)) {
                $io->info("No tables found in database '{$data['databaseName']}'.");
                return Command::SUCCESS;
            }

            $io->table(['Table', 'Size'], $tableSizes);

        } catch (Exception $e) {
            $io->error('Error retrieving table sizes: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function formatFileSize(float $bytes): string
    {
        if ($bytes < 1024) {
            return (int) $bytes . ' bytes';
        } elseif ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 2, '.', '') . ' KB';
        } elseif ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2, '.', '') . ' MB';
        } else {
            return number_format($bytes / (1024 * 1024 * 1024), 2, '.', '') . ' GB';
        }
    }
}
