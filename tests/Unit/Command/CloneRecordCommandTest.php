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

        $domain = \Mockery::mock('alias:Danilocgsilva\EntityClone\Domain');
        $domain->shouldReceive('getPdoFromDatabaseAccessId')
            ->with('src-conn', \Mockery::any())
            ->andReturn($sourcePdo);
        $domain->shouldReceive('getPdoFromDatabaseAccessId')
            ->with('tgt-conn', \Mockery::any())
            ->andReturn($targetPdo);

        $domain->shouldReceive('listDatabases')->with($sourcePdo, true)->andReturn(['db_src']);
        $domain->shouldReceive('listDatabases')->with($targetPdo, true)->andReturn(['db_tgt']);
        $domain->shouldReceive('listTables')->with($sourcePdo, 'db_src')->andReturn(['users']);
        $domain->shouldReceive('cloneRecordSecure')
            ->with($sourcePdo, $targetPdo, 'users', '42')
            ->andReturnTrue();

        // BaseDataCollector creates the EM via Helpers::createEntityManager()
        $em = \Mockery::mock(EntityManagerInterface::class);
        $helpers = \Mockery::mock('alias:Danilocgsilva\EntityCloneCli\Helpers');
        $helpers->shouldReceive('createEntityManager')->andReturn($em);

        // CloneRecordDataCollector asks interactively for the two connection ids
        $this->tester->setInputs(['src-conn', 'tgt-conn']);

        $this->tester->execute([
            '--source-database-name' => 'db_src',
            '--target-database-name' => 'db_tgt',
            '--table-name'           => 'users',
            '--record-id'            => '42',
        ], ['interactive' => true]);

        $output = $this->tester->getDisplay();

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringContainsString('Clone Database Record', $output);
        $this->assertStringContainsString('Record with ID 42 cloned successfully', $output);
    }
}
