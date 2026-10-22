<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\QuestionHelper;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use Danilocgsilva\EntityClone\Domain;

class UpdateConnectionDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        try {
            $connectionId = (int) $this->requireOption('connection-id', 'Please enter the database connection ID to update:');
            if (!$connectionId) {
                return null;
            }

            $entityManager = Helpers::createEntityManager();
            $databaseAccess = $entityManager->getRepository(DatabaseAccess::class)->find($connectionId);
            
            if (!$databaseAccess) {
                $this->io->error("Database connection with ID {$connectionId} not found.");
                return null;
            }

            /** @var \Symfony\Component\Console\Helper\QuestionHelper */
            $helper = $this->getHelper('question');

            // Connection name
            $connectionNameQuestion = new \Symfony\Component\Console\Question\Question(
                'Enter the connection name (leave empty to keep current): ',
                $databaseAccess->getName()
            );
            $connectionName = $helper->ask($this->input, $this->output, $connectionNameQuestion);
            if ($connectionName !== null) {
                $connectionName = trim($connectionName);
                if ($connectionName === '') {
                    $connectionName = $databaseAccess->getName();
                }
            } else {
                $connectionName = $databaseAccess->getName();
            }

            // Host
            $hostQuestion = new \Symfony\Component\Console\Question\Question(
                'Enter the host (leave empty to keep current): ',
                $databaseAccess->getHost()
            );
            $host = $helper->ask($this->input, $this->output, $hostQuestion);
            if ($host !== null) {
                $host = trim($host);
                if ($host === '') {
                    $host = $databaseAccess->getHost();
                }
            } else {
                $host = $databaseAccess->getHost();
            }

            // User
            $userQuestion = new \Symfony\Component\Console\Question\Question(
                'Enter the user (leave empty to keep current): ',
                $databaseAccess->getUser()
            );
            $user = $helper->ask($this->input, $this->output, $userQuestion);
            if ($user !== null) {
                $user = trim($user);
                if ($user === '') {
                    $user = $databaseAccess->getUser();
                }
            } else {
                $user = $databaseAccess->getUser();
            }

            // Database name
            $dbQuestion = new \Symfony\Component\Console\Question\Question(
                'Enter the database name (leave empty to keep current): ',
                $databaseAccess->getDatabaseName() ?? ''
            );
            $databaseName = $helper->ask($this->input, $this->output, $dbQuestion);
            if ($databaseName !== null) {
                $databaseName = trim($databaseName);
                if ($databaseName === '') {
                    $databaseName = $databaseAccess->getDatabaseName();
                }
            } else {
                $databaseName = $databaseAccess->getDatabaseName();
            }

            // Password
            $passwordQuestion = new \Symfony\Component\Console\Question\Question('Enter the password (leave empty to keep current): ');
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setHiddenFallback(false);
            $password = $helper->ask($this->input, $this->output, $passwordQuestion);
            
            if ($password !== null) {
                $password = trim($password);
                if ($password === '') {
                    $password = $databaseAccess->getPassword();
                }
            } else {
                $password = $databaseAccess->getPassword();
            }

            // Port
            $portQuestion = new \Symfony\Component\Console\Question\Question(
                'Enter the port [default: 3306] (leave empty to keep current): ',
                (string)$databaseAccess->getPort()
            );
            $port = trim($helper->ask($this->input, $this->output, $portQuestion));
            if ($port === '') {
                $port = (string)$databaseAccess->getPort();
            }

            // Display summary
            $this->io->writeln('<info>Connection details to be updated:</info>');
            $this->io->writeln([
                'ID: <comment>' . $connectionId . '</comment>',
                'Name: <comment>' . $connectionName . '</comment>',
                'Host: <comment>' . $host . '</comment>',
                'User: <comment>' . $user . '</comment>',
                'Database: <comment>' . ($databaseName ?: 'Not provided') . '</comment>',
                'Port: <comment>' . $port . '</comment>',
            ]);

            return [
                'connectionId' => $connectionId,
                'name' => $connectionName,
                'host' => $host,
                'user' => $user,
                'password' => $password,
                'databaseName' => $databaseName,
                'port' => (int)$port
            ];
        } catch (\Exception $e) {
            $this->io->error('Error collecting connection data: ' . $e->getMessage());
            return null;
        }
    }

    private function getHelper(string $helperName): object
    {
        // This method is needed to access the question helper from the parent class
        // Since we can't directly call parent::getHelper() in this context,
        // we'll use a different approach by accessing it through the input/output
        return $this->input->getHelper($helperName);
    }
}
