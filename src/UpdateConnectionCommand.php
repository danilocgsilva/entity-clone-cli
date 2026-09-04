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
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;

#[AsCommand(
    name: 'app:update-connection',
    description: 'Update an existing database connection.'
)]
class UpdateConnectionCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'connection-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Database connection ID to update'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Update Database Connection');

        try {
            $connectionId = (int) $this->requireOption($input, $io, 'connection-id', 'Please enter the database connection ID to update:');
            if (!$connectionId) return Command::FAILURE;

            $entityManager = $this->createEntityManager();

            // Find the connection
            $databaseAccess = $entityManager->getRepository(DatabaseAccess::class)->find($connectionId);
            
            if (!$databaseAccess) {
                $io->error("Database connection with ID {$connectionId} not found.");
                return Command::FAILURE;
            }

            /** @var \Symfony\Component\Console\Helper\QuestionHelper */
            $helper = $this->getHelper('question');

            // Connection name (optional)
            $connectionNameQuestion = new Question(
                'Enter the connection name (leave empty to keep current): ',
                $databaseAccess->getName()
            );
            $connectionName = $helper->ask($input, $output, $connectionNameQuestion);
            if ($connectionName !== null) {
                $connectionName = trim($connectionName);
                if ($connectionName === '') {
                    $connectionName = $databaseAccess->getName();
                }
            } else {
                $connectionName = $databaseAccess->getName();
            }

            // Host (optional)
            $hostQuestion = new Question(
                'Enter the host (leave empty to keep current): ',
                $databaseAccess->getHost()
            );
            $host = $helper->ask($input, $output, $hostQuestion);
            if ($host !== null) {
                $host = trim($host);
                if ($host === '') {
                    $host = $databaseAccess->getHost();
                }
            } else {
                $host = $databaseAccess->getHost();
            }

            // User (optional)
            $userQuestion = new Question(
                'Enter the user (leave empty to keep current): ',
                $databaseAccess->getUser()
            );
            $user = $helper->ask($input, $output, $userQuestion);
            if ($user !== null) {
                $user = trim($user);
                if ($user === '') {
                    $user = $databaseAccess->getUser();
                }
            } else {
                $user = $databaseAccess->getUser();
            }

            // Database (optional)
            $dbQuestion = new Question(
                'Enter the database name (leave empty to keep current): ',
                $databaseAccess->getDatabaseName() ?? ''
            );
            $databaseName = $helper->ask($input, $output, $dbQuestion);
            if ($databaseName !== null) {
                $databaseName = trim($databaseName);
                if ($databaseName === '') {
                    $databaseName = $databaseAccess->getDatabaseName();
                }
            } else {
                $databaseName = $databaseAccess->getDatabaseName();
            }

            // Password (optional - hidden input)
            $passwordQuestion = new Question('Enter the password (leave empty to keep current): ');
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $passwordQuestion);
            
            if ($password !== null) {
                $password = trim($password);
                if ($password === '') {
                    $password = $databaseAccess->getPassword();
                }
            } else {
                $password = $databaseAccess->getPassword();
            }

            // Port (optional with default)
            $portQuestion = new Question(
                'Enter the port [default: 3306] (leave empty to keep current): ',
                (string)$databaseAccess->getPort()
            );
            $port = trim($helper->ask($input, $output, $portQuestion));
            if ($port === '') {
                $port = (string)$databaseAccess->getPort();
            }

            // Summary
            $output->writeln('<info>Connection details to be updated:</info>');
            $output->writeln([
                'ID: <comment>' . $connectionId . '</comment>',
                'Name: <comment>' . $connectionName . '</comment>',
                'Host: <comment>' . $host . '</comment>',
                'User: <comment>' . $user . '</comment>',
                'Database: <comment>' . ($databaseName ?: 'Not provided') . '</comment>',
                'Port: <comment>' . $port . '</comment>',
            ]);

            // Update the connection
            $databaseAccess
                ->setName($connectionName)
                ->setHost($host)
                ->setUser($user)
                ->setPassword($password)
                ->setDatabaseName($databaseName)
                ->setPort((int)$port);

            $entityManager->flush();

            $io->success("Database connection '{$connectionName}' (ID: {$connectionId}) updated successfully.");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error updating database connection: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
