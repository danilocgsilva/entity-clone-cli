# Unit Test for CreateTableFromSourceCommand

Based on the code structure and existing test patterns in your project, here's a comprehensive unit test:

```php
<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Tests\Unit\Command;

use Danilocgsilva\EntityClone\Command\BaseCommand;
use Danilocgsilva\EntityClone\Command\CreateTableFromSourceCommand;
use Danilocgsilva\EntityClone\Exceptions\MissingTargetDatabase;
use Danilocgsilva\EntityClone\Exceptions\TargetTableAlreadyExists;
use Danilocgsilva\EntityClone\Helpers;
use Danilocgsilva\EntityClone\Domain;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

class CreateTableFromSourceCommandTest extends BaseCommandTest
{
    private CommandTester $commandTester;
    
    #[\PHPUnit\Framework\Attributes\Before]
    public function setUp(): void
    {
        $command = new CreateTableFromSourceCommand();
        $this->commandTester = new CommandTester($command);
    }

    public function testCommandConfiguration(): void
    {
        $command = new CreateTableFromSourceCommand();
        $this->assertEquals('app:table:create-from-source', $command->getName());
        $this->assertEquals('Create a table in target database from source database structure', $command->getDescription());
        
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('source-connection'));
        $this->assertTrue($definition->hasOption('target-connection'));
        $this->assertTrue($definition->hasOption('database-name'));
        $this->assertTrue($definition->hasOption('table-name'));
    }

    public function testExecuteSuccess(): void
    {
        $sourceConnection = $this->createMock(DatabaseAccess::class);
        $targetConnection = $this->createMock(DatabaseAccess::class);
        $sourceRepository = $this->createMock(EntityRepository::class);
        $targetRepository = $this->createMock(EntityRepository::class);
        $entityManager = $this->createMock(EntityManager::class);
        $helpers = $this->createMock(Helpers::class);

        $sourceRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($sourceConnection);

        $targetRepository->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn($targetConnection);

        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturnMap([
                [DatabaseAccess::class, $sourceRepository],
                [DatabaseAccess::class, $targetRepository],
            ]);

        $helpers->expects($this->once())
            ->method('createEntityManager')
            ->willReturn($entityManager);

        Domain::expects($this->once())
            ->method('createTableFromSource')
            ->with(1, 2, 'test_db', 'test_table', $entityManager);

        $this->commandTester->setInputs([
            '1',  // source-connection
            '2',  // target-connection
            'test_db',  // database-name
            'test_table',  // table-name
        ]);

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('successfully created', $output);
    }

    public function testExecuteSourceConnectionNotFound(): void
    {
        $sourceRepository = $this->createMock(EntityRepository::class);
        $entityManager = $this->createMock(EntityManager::class);
        $helpers = $this->createMock(Helpers::class);

        $sourceRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn(null);

        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($sourceRepository);

        $helpers->expects($this->once())
            ->method('createEntityManager')
            ->willReturn($entityManager);

        $this->commandTester->setInputs([
            '1',
            '2',
            'test_db',
            'test_table',
        ]);

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Source connection with ID 1 not found', $output);
    }

    public function testExecuteTargetConnectionNotFound(): void
    {
        $sourceConnection = $this->createMock(DatabaseAccess::class);
        $targetRepository = $this->createMock(EntityRepository::class);
        $entityManager = $this->createMock(EntityManager::class);
        $helpers = $this->createMock(Helpers::class);

        $sourceRepository = $this->createMock(EntityRepository::class);
        $sourceRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($sourceConnection);

        $targetRepository->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn(null);

        $entityManager->expects($this->exactly(2))
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturnMap([
                [DatabaseAccess::class, $sourceRepository],
                [DatabaseAccess::class, $targetRepository],
            ]);

        $helpers->expects($this->once())
            ->method('createEntityManager')
            ->willReturn($entityManager);

        $this->commandTester->setInputs([
            '1',
            '2',
            'test_db',
            'test_table',
        ]);

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Target connection with ID 2 not found', $output);
    }

    public function testExecuteTableAlreadyExists(): void
    {
        $sourceConnection = $this->createMock(DatabaseAccess::class);
        $targetConnection = $this->createMock(DatabaseAccess::class);
        $sourceRepository = $this->createMock(EntityRepository::class);
        $targetRepository = $this->createMock(EntityRepository::class);
        $entityManager = $this->createMock(EntityManager::class);
        $helpers = $this->createMock(Helpers::class);

        $sourceRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($sourceConnection);

        $targetRepository->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn($targetConnection);

        $entityManager->expects($this->exactly(2))
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturnMap([
                [DatabaseAccess::class, $sourceRepository],
                [DatabaseAccess::class, $targetRepository],
            ]);

        $helpers->expects($this->once())
            ->method('createEntityManager')
            ->willReturn($entityManager);

        Domain::expects($this->once())
            ->method('createTableFromSource')
            ->willThrowException(new TargetTableAlreadyExists('test_db', 'test_table'));

        $this->commandTester->setInputs([
            '1',
            '2',
            'test_db',
            'test_table',
        ]);

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('already exists', $output);
    }

    public function testExecuteMissingTargetDatabase(): void
    {
        $sourceConnection = $this->createMock(DatabaseAccess::class);
        $targetConnection = $this->createMock(DatabaseAccess::class);
        $sourceRepository = $this->createMock(EntityRepository::class);
        $targetRepository = $this->createMock(EntityRepository::class);
        $entityManager = $this->createMock(EntityManager::class);
        $helpers = $this->createMock(Helpers::class);

        $sourceRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($sourceConnection);

        $targetRepository->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn($targetConnection);

        $entityManager->expects($this->exactly(2))
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturnMap([
                [DatabaseAccess::class, $sourceRepository],
                [DatabaseAccess::class, $targetRepository],
            ]);

        $helpers->expects($this->once())
            ->method('createEntityManager')
            ->willReturn($entityManager);

        Domain::expects($this->once())
            ->method('createTableFromSource')
            ->willThrowException(new MissingTargetDatabase('test_db'));

        $this->commandTester->setInputs([
            '1',
            '2',
            'test_db',
            'test_table',
        ]);

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Error creating table', $output);
    }

    public function testExecuteGeneralException(): void
    {
        $sourceConnection = $this->createMock(DatabaseAccess::class);
        $targetConnection = $this->createMock(DatabaseAccess::class);
        $sourceRepository = $this->createMock(EntityRepository::class);
        $targetRepository = $this->createMock(EntityRepository::class);
        $entityManager = $this->createMock(EntityManager::class);
        $helpers = $this->createMock(Helpers::class);

        $sourceRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($sourceConnection);

        $targetRepository->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn($targetConnection);

        $entityManager->expects($this->exactly(2))
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturnMap([
                [DatabaseAccess::class, $sourceRepository],
                [DatabaseAccess::class, $targetRepository],
            ]);

        $helpers->expects($this->once())
            ->method('createEntityManager')
            ->willReturn($entityManager);

        Domain::expects($this->once())
            ->method('createTableFromSource')
            ->willThrowException(new \RuntimeException('Some error occurred'));

        $this->commandTester->setInputs([
            '1',
            '2',
            'test_db',
            'test_table',
        ]);

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Error creating table', $output);
    }

    public function testExecuteWithOptionValues(): void
    {
        $sourceConnection = $this->createMock(DatabaseAccess::class);
        $targetConnection = $this->createMock(DatabaseAccess::class);
        $sourceRepository = $this->createMock(EntityRepository::class);
        $targetRepository = $this->createMock(EntityRepository::class);
        $entityManager = $this->createMock(EntityManager::class);
        $helpers = $this->createMock(Helpers::class);

        $sourceRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($sourceConnection);

        $targetRepository->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn($targetConnection);

        $entityManager->expects($this->exactly(2))
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturnMap([
                [DatabaseAccess::class, $sourceRepository],
                [DatabaseAccess::class, $targetRepository],
            ]);

        $helpers->expects($this->once())
            ->method('createEntityManager')
            ->willReturn($entityManager);

        Domain::expects($this->once())
            ->method('createTableFromSource')
            ->with(1, 2, 'test_db', 'test_table', $entityManager);

        $input = new ArrayInput([
            'source-connection' => '1',
            'target-connection' => '2',
            'database-name' => 'test_db',
            'table-name' => 'test_table',
        ]);

        $this->commandTester->setInputs([
            '1',
            '2',
            'test_db',
            'test_table',
        ]);

        $exitCode = $this->commandTester->execute([
            'source-connection' => '1',
            'target-connection' => '2',
            'database-name' => 'test_db',
            'table-name' => 'test_table',
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
    }
}
```

## Key Testing Points

| Test Case | Coverage |
|-----------|----------|
| `testCommandConfiguration` | Command name, description, options |
| `testExecuteSuccess` | Happy path - all connections valid |
| `testExecuteSourceConnectionNotFound` | Source connection validation error |
| `testExecuteTargetConnectionNotFound` | Target connection validation error |
| `testExecuteTableAlreadyExists` | TargetTableAlreadyExists exception |
| `testExecuteMissingTargetDatabase` | MissingTargetDatabase exception |
| `testExecuteGeneralException` | Generic exception handling |
| `testExecuteWithOptionValues` | Command-line options working |

## Notes

1. **Mocked Dependencies**: I've mocked `EntityManager`, `EntityRepository`, `Helpers`, and `Domain` to isolate the command logic.

2. **Follows Your Pattern**: Matches the structure of your existing tests in `tests/Unit/Command/`.

3. **Remove Debug Code**: Consider removing the `var_dump(get_class($e))` statements in production code for cleaner output.

4. **Integration Test**: You may also want an integration test that actually connects to a database to verify the table creation works end-to-end.

Would you like me to also create an integration test for this command?