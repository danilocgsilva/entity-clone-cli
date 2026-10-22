<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityCloneCli\Helpers;

class RegisterConnectionDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        try {
            $helper = $this->getHelper('question');
            
            $connectionNameQuestion = new Question('Enter the connection name to make it more identifiable: ');
            $connectionName = $helper->ask($this->input, $this->output, $connectionNameQuestion);
            if ($connectionName === null || trim($connectionName) === '') {
                $this->io->error('Connection name cannot be empty. Exiting.');
                return null;
            }
            $connectionName = trim($connectionName);

            $hostQuestion = new Question('Enter the host: ');
            $host = $helper->ask($this->input, $this->output, $hostQuestion);
            if ($host === null || trim($host) === '') {
                $this->io->error('Host cannot be empty. Exiting.');
                return null;
            }
            $host = trim($host);

            $userQuestion = new Question('Enter the user: ');
            $user = $helper->ask($this->input, $this->output, $userQuestion);
            if ($user === null || trim($user) === '') {
                $this->io->error('User cannot be empty. Exiting.');
                return null;
            }
            $user = trim($user);

            $dbQuestion = new Question('Enter the database name (optional): ');
            $databaseName = $helper->ask($this->input, $this->output, $dbQuestion);
            if ($databaseName !== null) {
                $databaseName = trim($databaseName);
            }

            $passwordQuestion = new Question('Enter the password: ');
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setHiddenFallback(false); // Fail if terminal doesn't support hiding
            $password = $helper->ask($this->input, $this->output, $passwordQuestion);
            if ($password === null || trim($password) === '') {
                $this->io->error('Password cannot be empty. Exiting.');
                return null;
            }
            $password = trim($password);

            $portQuestion = new Question('Enter the port [default: 3306]: ', '3306');
            $port = trim($helper->ask($this->input, $this->output, $portQuestion));
            if ($port === '') {
                $port = '3306';
            }

            $this->io->writeln('<info>Connection details collected successfully!</info>');
            $this->io->writeln([
                'Host: <comment>' . $host . '</comment>',
                'User: <comment>' . $user . '</comment>',
                'Database: <comment>' . ($databaseName ?: 'Not provided') . '</comment>',
                'Port: <comment>' . $port . '</comment>',
            ]);

            return [
                'connectionName' => $connectionName,
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

    private function getHelper(string $name): QuestionHelper
    {
        // This is a workaround to access the helper from the base class context
        // In a real implementation, you might want to pass this in constructor or 
        // use a different approach to handle helpers
        return $this->getQuestionHelper();
    }

    private function getQuestionHelper(): QuestionHelper
    {
        // This method should be implemented in the child class or via dependency injection
        // For now, we'll assume it's accessible through the command context
        return $this->questionHelper ?? null;
    }
}