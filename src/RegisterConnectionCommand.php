<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityClone\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:register-connection',
    description: 'Register a database connection credentials.'
)]
class RegisterConnectionCommand extends Command
{
    private InputInterface $input;
    private OutputInterface $output;
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        
        /** @var \Symfony\Component\Console\Helper\QuestionHelper */
        $helper = $this->getHelper('question');

        $hostQuestion = new Question('Enter the host: ');
        $hostQuestion->setValidator(function ($value) {
            if (empty(trim($value))) {
                throw new \Exception('Host cannot be empty.');
            }
            return trim($value);
        });
        $host = $helper->ask($input, $output, $hostQuestion);

        $userQuestion = new Question('Enter the user: ');
        $userQuestion->setValidator(function ($value) {
            if (empty(trim($value))) {
                throw new \Exception('User cannot be empty.');
            }
            return trim($value);
        });
        $user = $helper->ask($input, $output, $userQuestion);

        $dbQuestion = new Question('Enter the database name: ');
        $dbQuestion->setValidator(function ($value) {
            if (empty(trim($value))) {
                throw new \Exception('Database name cannot be empty.');
            }
            return trim($value);
        });
        $databaseName = $helper->ask($input, $output, $dbQuestion);

        $passwordQuestion = new Question('Enter the password: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false); // Fail if terminal doesn't support hiding
        $passwordQuestion->setValidator(function ($value) {
            if (empty(trim($value))) {
                throw new \Exception('Password cannot be empty.');
            }
            return trim($value);
        });
        $password = $helper->ask($input, $output, $passwordQuestion);

        $portQuestion = new Question('Enter the port [default: 3306]: ', '3306');
        $port = trim($helper->ask($input, $output, $portQuestion));

        $output->writeln('<info>Connection details collected successfully!</info>');
        $output->writeln([
            'Host: <comment>' . $host . '</comment>',
            'User: <comment>' . $user . '</comment>',
            'Database: <comment>' . $databaseName . '</comment>',
            'Port: <comment>' . $port . '</comment>',
        ]);

        $entityManager = EntityManagerFactory::create(
            projectRoot: __DIR__ . '/..',
            entityPaths: [__DIR__ . '/../src/Entities'],
        );

        $databaseAccess = new DatabaseAccess()
            ->setHost($host)
            ->setUser($user)
            ->setPassword($password)
            ->setDatabaseName($databaseName)
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