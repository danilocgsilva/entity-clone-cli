<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Tests\Unit\Command;

use Danilocgsilva\EntityCloneCli\Command\CloneRecordCommand;
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\CloneRecordDataCollector;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

class CloneRecordCommandTest extends TestCase
{
    private DatabaseConnectionLister $connectionLister;
    private CloneRecordCommand $command;
    private CommandTester $tester;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->connectionLister = $this->createMock(DatabaseConnectionLister::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->command = new CloneRecordCommand($this->connectionLister, $this->entityManager);
        $this->tester = new CommandTester($this->command);
    }

    public function testConfigureAddsAllOptions(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('source-database-name'));
        $this->assertTrue($definition->hasOption('target-database-name'));
        $this->assertTrue($definition->hasOption('table-name'));
        $this->assertTrue($definition->hasOption('record-id'));
    }

    public function testExecuteReturnsFailureWhenCollectorReturnsNoData(): void
    {
        $this->tester->execute([]);
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testExecuteHappyPath(): void
    {
        $sourcePdo = $this->createMock(\PDO::class);
        $targetPdo = $this->createMock(\PDO::class);

        $dbAccess1 = new DatabaseAccess();
        $dbAccess1->setId(1);
        $dbAccess2 = new DatabaseAccess();
        $dbAccess2->setId(2);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn([$dbAccess1, $dbAccess2]);
        $this->entityManager->method('getRepository')->willReturn($repository);

        $connectionLister = $this->connectionLister;
        $connectionLister->method('listConnections');

        $em = $this->entityManager;

        $command = new class($connectionLister, $em, $sourcePdo, $targetPdo) extends CloneRecordCommand {
            public function __construct(
                DatabaseConnectionLister $connectionLister,
                EntityManagerInterface $em,
                private \PDO $sourcePdo,
                private \PDO $targetPdo,
            ) {
                parent::__construct($connectionLister, $em);
            }

            protected function getPdoFromDatabaseAccessId(int $id, EntityManagerInterface $em): \PDO
            {
                return $id === 1 ? $this->sourcePdo : $this->targetPdo;
            }

            protected function listDatabases(\PDO $pdo, bool $excludeSystem): array
            {
                return $pdo === $this->sourcePdo ? ['db_src'] : ['db_tgt'];
            }

            protected function listTables(\PDO $pdo, string $database): array
            {
                return ['users'];
            }

            protected function cloneRecordSecure(\PDO $source, \PDO $target, string $table, string|int $id): bool
            {
                return true;
            }

            protected function makeDataCollector(InputInterface $input, SymfonyStyle $io, EntityManagerInterface $em): CloneRecordDataCollector
            {
                $sourcePdo = $this->sourcePdo;
                $targetPdo = $this->targetPdo;

                return new class($input, $io, $em, $sourcePdo, $targetPdo) extends CloneRecordDataCollector {
                    public function __construct(
                        InputInterface $input,
                        SymfonyStyle $io,
                        EntityManagerInterface $em,
                        private \PDO $sourcePdo,
                        private \PDO $targetPdo,
                    ) {
                        parent::__construct($input, $io, $em);
                    }

                    protected function createPdoFromDatabaseConnectionEntity(DatabaseAccess $dbAccess): \PDO
                    {
                        return $dbAccess->getId() === 1 ? $this->sourcePdo : $this->targetPdo;
                    }

                    protected function listDatabases(\PDO $pdo, bool $excludeSystem): array
                    {
                        return $pdo === $this->sourcePdo ? ['db_src'] : ['db_tgt'];
                    }
                };
            }
        };

        $tester = new CommandTester($command);
        $tester->execute([
            '--source-database-name' => 'db_src',
            '--target-database-name' => 'db_tgt',
            '--table-name'           => 'users',
            '--record-id'            => '42',
        ]);

        $output = $tester->getDisplay();

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Clone Database Record', $output);
        $this->assertStringContainsString('Record with ID 42 cloned successfully', $output);
    }
}
