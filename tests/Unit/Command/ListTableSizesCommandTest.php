<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityClone\Domain;
use Danilocgsilva\EntityCloneCli\Command\ListTableSizesCommand;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;
use Exception;
use PDO;
use PDOStatement;

class ListTableSizesCommandTest extends TestCase
{
    private ListTableSizesCommand $command;
    private EntityManagerInterface $entityManager;
    private DatabaseConnectionLister $connectionLister;

        protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connectionLister = $this->createMock(DatabaseConnectionLister::class);
        
        $this->command = new class($this->connectionLister) extends ListTableSizesCommand {
            public function __construct(DatabaseConnectionLister $connectionLister)
            {
                parent::__construct($connectionLister);
            }

            protected function createEntityManager(): EntityManagerInterface
            {
                return $this->createMock(EntityManagerInterface::class);
            }

            protected function createPdoFromDatabaseConnectionEntity(object $connection): PDO
            {
                return $this->createMock(PDO::class);
            }
        };
    }

    public function testExecuteSuccess(): void
    {
        $connectionId = 1;
        
        $databaseAccess = $this->createMock(DatabaseAccess::class);
        $databaseAccess->expects($this->once())
            ->method('getId')
            ->willReturn($connectionId);
        
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with($connectionId)
            ->willReturn($databaseAccess);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
        
        $this->connectionLister->expects($this->once())
            ->method('listConnections')
            ->with($this->isInstanceOf(SymfonyStyle::class));
        
        $pdo = $this->createMock(PDO::class);
        $pdoStatement = $this->createMock(PDOStatement::class);
        $pdoStatement->expects($this->once())
            ->method('execute');
        $pdoStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_COLUMN)
            ->willReturn(['test_db', 'production_db']);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with('SHOW DATABASES')
            ->willReturn($pdoStatement);
        
        $tableSizes = [
            ['table' => 'users', 'size' => 1048576],
            ['table' => 'orders', 'size' => 2097152],
            ['table' => 'products', 'size' => 524288]
        ];
        
        $this->connectionLister->expects($this->once())
            ->method('listConnections');
        
        $input = new ArrayInput(['--connection-id' => $connectionId]);
        $output = new BufferedOutput();
        
        $questionHelper = $this->createMock(QuestionHelper::class);
        $questionHelper->expects($this->once())
            ->method('ask')
            ->willReturn('1');
        
        $this->command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet([
            'question' => $questionHelper
        ]));
        
        $result = $this->command->run($input, $output);
        
        $this->assertSame(Command::SUCCESS, $result);
        $this->assertStringContainsString('Database Table Sizes List', $output->fetch());
    }

    public function testExecuteWithInvalidConnectionId(): void
    {
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
        
        $this->connectionLister->expects($this->once())
            ->method('listConnections');
        
        $input = new ArrayInput(['--connection-id' => 999]);
        $output = new BufferedOutput();
        
        $result = $this->command->run($input, $output);
        
        $this->assertSame(Command::FAILURE, $result);
        $this->assertStringContainsString('not found', $output->fetch());
    }

    public function testExecuteWithNoDatabases(): void
    {
        $connectionId = 1;
        
        $databaseAccess = $this->createMock(DatabaseAccess::class);
        $databaseAccess->expects($this->once())
            ->method('getId')
            ->willReturn($connectionId);
        
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with($connectionId)
            ->willReturn($databaseAccess);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
        
        $this->connectionLister->expects($this->once())
            ->method('listConnections');
        
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with('SHOW DATABASES')
            ->willReturn($this->createMock(PDOStatement::class));
        $pdoStatement = $this->createMock(PDOStatement::class);
        $pdoStatement->expects($this->once())
            ->method('execute');
        $pdoStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_COLUMN)
            ->willReturn([]);
        
        $input = new ArrayInput(['--connection-id' => $connectionId]);
        $output = new BufferedOutput();
        
        $result = $this->command->run($input, $output);
        
        $this->assertSame(Command::FAILURE, $result);
        $this->assertStringContainsString('No databases found', $output->fetch());
    }

    public function testExecuteWithNoTables(): void
    {
        $connectionId = 1;
        
        $databaseAccess = $this->createMock(DatabaseAccess::class);
        $databaseAccess->expects($this->once())
            ->method('getId')
            ->willReturn($connectionId);
        
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with($connectionId)
            ->willReturn($databaseAccess);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
        
        $this->connectionLister->expects($this->once())
            ->method('listConnections');
        
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with('SHOW DATABASES')
            ->willReturn($this->createMock(PDOStatement::class));
        $pdoStatement = $this->createMock(PDOStatement::class);
        $pdoStatement->expects($this->once())
            ->method('execute');
        $pdoStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_COLUMN)
            ->willReturn(['test_db']);
        
        $input = new ArrayInput(['--connection-id' => $connectionId]);
        $output = new BufferedOutput();
        
        $questionHelper = $this->createMock(QuestionHelper::class);
        $questionHelper->expects($this->once())
            ->method('ask')
            ->willReturn('1');
        
        $this->command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet([
            'question' => $questionHelper
        ]));
        
        $result = $this->command->run($input, $output);
        
        $this->assertSame(Command::SUCCESS, $result);
        $this->assertStringContainsString('No tables found', $output->fetch());
    }

    public function testExecuteWithInvalidDatabaseSelection(): void
    {
        $connectionId = 1;
        
        $databaseAccess = $this->createMock(DatabaseAccess::class);
        $databaseAccess->expects($this->once())
            ->method('getId')
            ->willReturn($connectionId);
        
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with($connectionId)
            ->willReturn($databaseAccess);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
        
        $this->connectionLister->expects($this->once())
            ->method('listConnections');
        
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with('SHOW DATABASES')
            ->willReturn($this->createMock(PDOStatement::class));
        $pdoStatement = $this->createMock(PDOStatement::class);
        $pdoStatement->expects($this->once())
            ->method('execute');
        $pdoStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_COLUMN)
            ->willReturn(['test_db', 'production_db']);
        
        $input = new ArrayInput(['--connection-id' => $connectionId]);
        $output = new BufferedOutput();
        
        $questionHelper = $this->createMock(QuestionHelper::class);
        $questionHelper->expects($this->exactly(2))
            ->method('ask')
            ->willReturnCallback(function (InputInterface $input, OutputInterface $output, Question $question) {
                if (str_contains($question->getQuestion(), 'database')) {
                    return '5'; // Invalid selection
                }
                return '1';
            });
        
        $this->command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet([
            'question' => $questionHelper
        ]));
        
        $result = $this->command->run($input, $output);
        
        $this->assertSame(Command::FAILURE, $result);
    }

    public function testExecuteWithException(): void
    {
        $connectionId = 1;
        
        $databaseAccess = $this->createMock(DatabaseAccess::class);
        $databaseAccess->expects($this->once())
            ->method('getId')
            ->willReturn($connectionId);
        
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with($connectionId)
            ->willReturn($databaseAccess);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
        
        $this->connectionLister->expects($this->once())
            ->method('listConnections');
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willThrowException(new Exception('Connection failed'));
        
        $input = new ArrayInput(['--connection-id' => $connectionId]);
        $output = new BufferedOutput();
        
        $result = $this->command->run($input, $output);
        
        $this->assertSame(Command::FAILURE, $result);
        $this->assertStringContainsString('Error retrieving table sizes', $output->fetch());
    }
}