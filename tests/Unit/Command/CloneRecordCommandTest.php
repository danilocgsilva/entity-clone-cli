<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Tests\Unit\Command;

use Danilocgsilva\EntityCloneCli\Command\CloneRecordCommand;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CloneRecordCommandTest extends TestCase
{
    private DatabaseConnectionLister $connectionLister;
    private CloneRecordCommand $command;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->connectionLister = \Mockery::mock(DatabaseConnectionLister::class);
        $this->connectionLister->shouldReceive('listConnections')->andReturnNull();

        $this->command = new CloneRecordCommand($this->connectionLister);
        $this->tester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
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
        $sourcePdo = \Mockery::mock(\PDO::class);
        $targetPdo = \Mockery::mock(\PDO::class);

        // --- Static Helpers mock (needed by BaseDataCollector constructor) ---
        $em = \Mockery::mock(EntityManagerInterface::class);
        $helpers = \Mockery::mock('alias:Danilocgsilva\EntityCloneCli\Helpers');
        $helpers->shouldReceive('createEntityManager')->andReturn($em);

        // --- Static Domain mock ---
        $domain = \Mockery::mock('alias:Danilocgsilva\EntityClone\Domain');
        $domain->shouldReceive('getPdoFromDatabaseAccessId')
            ->andReturnUsing(function (string $id, $em) use ($sourcePdo, $targetPdo) {
                return $id === 'src-conn' ? $sourcePdo : $targetPdo;
            });

        $domain->shouldReceive('listDatabases')
            ->andReturnUsing(function ($pdo, bool $flag) use ($sourcePdo, $targetPdo) {
                return $pdo === $sourcePdo ? ['db_src'] : ['db_tgt'];
            });

        $domain->shouldReceive('listTables')
            ->with($sourcePdo, 'db_src')
            ->andReturn(['users']);

        $domain->shouldReceive('cloneRecordSecure')
            ->with($sourcePdo, $targetPdo, 'users', '42')
            ->andReturnTrue();

        // Feed the interactive prompts the collector will ask for (connection IDs)
        $this->tester->setInputs(['src-conn', 'tgt-conn']);

        $this->tester->execute([
            '--source-database-name' => 'db_src',
            '--target-database-name' => 'db_tgt',
            '--table-name'           => 'users',
            '--record-id'            => '42',
        ]);

        $output = $this->tester->getDisplay();

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringContainsString('Clone Database Record', $output);
        $this->assertStringContainsString('Record with ID 42 cloned successfully', $output);
    }
}