<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Danilocgsilva\EntityCloneCli\Command\ListTableSizesCommand;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityClone\Domain;
use PDO;
use Exception;

class ListTableSizesCommandTest extends TestCase
{
    public function testExecuteSuccess(): void
    {
        // Mock dependencies
        $connectionLister = $this->createMock(DatabaseConnectionLister::class);
        $databaseAccess = $this->createMock(DatabaseAccess::class);
        $pdo = $this->createMock(PDO::class);
        $domain = $this->createMock(Domain::class);

        // Mock the DatabaseConnectionLister
        $connectionLister->expects($this->once())
            ->method('listConnections')
            ->with($this->isInstanceOf(SymfonyStyle::class));

        // Mock the repository
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $entityManager = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);

        // Mock the repository find method
        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($databaseAccess);

        // Mock Domain::createPdoFromDatabaseConnectionEntity
        Domain::expects($this->once())
            ->method('createPdoFromDatabaseConnectionEntity')
            ->with($databaseAccess)
            ->willReturn($pdo);

        // Mock SHOW DATABASES query
        $databasesStmt = $this->createMock(\PDOStatement::class);
        $databasesStmt->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_COLUMN)
            ->willReturn(['db1', 'db2']);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with('SHOW DATABASES')
            ->willReturn($databasesStmt);

        // Mock Domain::listTableSizes
        Domain::expects($this->once())
            ->method('listTableSizes')
            ->with($pdo, 'db1')
            ->willReturn([
                ['table' => 'users', 'size' => '1024.5'],
                ['table' => 'posts', 'size' => '2048.0']
            ]);

        // Mock the input and output
        $input = new ArrayInput(['--connection-id' => '1']);
        $output = new BufferedOutput();

        // Create the command with mocks
        $command = new ListTableSizesCommand($connectionLister);
        $command->setEntityManager($entityManager); // This would need to be added to the command class

        // Mock the selectDatabase method to return a specific database
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('selectDatabase');
        $method->setAccessible(true);
        $method->invoke($command, $this->createMock(SymfonyStyle::class), ['db1', 'db2']);

        // Execute the command
        $result = $command->run($input, $output);

        // Assert the result
        $this->assertEquals(0, $result);
    }

    public function testExecuteNoConnectionFound(): void
    {
        $connectionLister = $this->createMock(DatabaseConnectionLister::class);
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $entityManager = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn(null);

        $input = new ArrayInput(['--connection-id' => '1']);
        $output = new BufferedOutput();

        $command = new ListTableSizesCommand($connectionLister);
        $command->setEntityManager($entityManager);

        $result = $command->run($input, $output);

        $this->assertEquals(1, $result);
    }

    public function testExecuteException(): void
    {
        $connectionLister = $this->createMock(DatabaseConnectionLister::class);
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $entityManager = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willThrowException(new Exception('Test exception'));

        $input = new ArrayInput(['--connection-id' => '1']);
        $output = new BufferedOutput();

        $command = new ListTableSizesCommand($connectionLister);
        $command->setEntityManager($entityManager);

        $result = $command->run($input, $output);

        $this->assertEquals(1, $result);
    }
}
