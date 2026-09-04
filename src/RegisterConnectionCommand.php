<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;

#[AsCommand(
    name: 'app:register-connection',
    description: 'Register a database connection credentials.'
)]
class RegisterConnectionCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var \Symfony\Component\Console\Helper\QuestionHelper */
        $helper = $this->getHelper('question');

        // Connection name (required)
        $connectionNameQuestion = new Question('Enter the connection name to make it more identifiable: ');
        $connectionName = $helper->ask($input, $output, $connectionNameQuestion);
        if ($connectionName === null || trim($connectionName) === '') {
            $io->error('Connection name cannot be empty. Exiting.');
            return Command::FAILURE;
        }
        $connectionName = trim($connectionName);

        // Host (required)
        $hostQuestion = new Question('Enter the host: ');
        $host = $helper->ask($input, $output, $hostQuestion);
        if ($host === null || trim($host) === '') {
            $io->error('Host cannot be empty. Exiting.');
            return Command::FAILURE;
        }
        $host = trim($host);

        // User (required)
        $userQuestion = new Question('Enter the user: ');
        $user = $helper->ask($input, $output, $userQuestion);
        if ($user === null || trim($user) === '') {
            $io->error('User cannot be empty. Exiting.');
            return Command::FAILURE;
        }
        $user = trim($user);

        // Database (optional)
        $dbQuestion = new Question('Enter the database name (optional): ');
        $databaseName = $helper->ask($input, $output, $dbQuestion);
        if ($databaseName !== null) {
            $databaseName = trim($databaseName);
        }

        // Password (required)
        $passwordQuestion = new Question('Enter the password: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false); // Fail if terminal doesn't support hiding
        $password = $helper->ask($input, $output, $passwordQuestion);
        if ($password === null || trim($password) === '') {
            $io->error('Password cannot be empty. Exiting.');
            return Command::FAILURE;
        }
        $password = trim($password);

        // Port (optional with default)
        $portQuestion = new Question('Enter the port [default: 3306]: ', '3306');
        $port = trim($helper->ask($input, $output, $portQuestion));
        if ($port === '') {
            $port = '3306';
        }

        // Summary
        $output->writeln('<info>Connection details collected successfully!</info>');
        $output->writeln([
            'Host: <comment>' . $host . '</comment>',
            'User: <comment>' . $user . '</comment>',
            'Database: <comment>' . ($databaseName ?: 'Not provided') . '</comment>',
            'Port: <comment>' . $port . '</comment>',
        ]);

        $entityManager = $this->createEntityManager();

        $databaseAccess = new DatabaseAccess()
            ->setName($connectionName)
            ->setHost($host)
            ->setUser($user)
            ->setPassword($password)
            ->setDatabaseName($databaseName) // Can be null
            ->setPort((int)$port);

        try {
            $entityManager->persist($databaseAccess);
            $entityManager->flush();

            $output->writeln('<info>Database connection registered successfully with ID: ' . $databaseAccess->getId() . '</info>');
        } catch (\Exception $e) {
            $output->writeln('<error>Error saving connection: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
