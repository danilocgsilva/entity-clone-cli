The command is not very unit-testable as-is because it:

* creates the `CloneRecordDataCollector` inside `execute()`
* calls several static methods on `Danilocgsilva\EntityClone\Domain`
* uses `Helpers::createEntityManager()` 

To write a proper isolated unit test I need to see how the rest of the code works.

Can you paste:

* `src/Command/BaseCommand.php`
* `src/Command/DataCollectors/CloneRecordDataCollector.php`
* `src/Command/DataCollectors/BaseDataCollector.php`
* the signature of `Domain::getPdoFromDatabaseAccessId`, `Domain::listDatabases`, `Domain::listTables` and `Domain::cloneRecordSecure`
* how the existing tests mock `Domain` static calls and `Helpers::createEntityManager`

With that I can make the test 100% realistic. In the meantime here is a test skeleton that works with the current code and Mockery aliasing for the static Domain facade. It shows the happy path and the two main failure paths.

```php
<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Tests\Unit\Command;

use Danilocgsilva\EntityCloneCli\Command\CloneRecordCommand;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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
        // CloneRecordDataCollector is instantiated inside execute, so we force
        // a failure by calling the command without the required options.
        // In a real test you would inject a test double for the collector.
        $this->tester->execute([]);
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testExecuteHappyPath(): void
    {
        // ---- Assumptions ----
        // 1. CloneRecordDataCollector::collect() returns the data array.
        // 2. Domain static methods are mocked with Mockery alias.
        // If your collector is interactive you need to feed SymfonyStyle input.

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
        $domain->shouldReceive('cloneRecordSecure')->with($sourcePdo, $targetPdo, 'users', '42')->andReturnTrue();

        // Helper
        $em = \Mockery::mock(\stdClass::class);
        $helpers = \Mockery::mock('alias:Danilocgsilva\EntityCloneCli\Helpers');
        $helpers->shouldReceive('createEntityManager')->andReturn($em);

        $input = new ArrayInput([
            '--source-database-name' => 'db_src',
            '--target-database-name' => 'db_tgt',
            '--table-name'           => 'users',
            '--record-id'            => '42',
        ], $this->command->getDefinition());

        $this->tester->execute([], ['interactive' => false]);

        // With the current implementation the collector will still ask interactively
        // for the connection ids. This test will only pass after the collector is
        // made injectable or the static calls are wrapped.
        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Clone Database Record', $output);
    }
}
```

Notes / what is blocking a clean unit test:

* `new CloneRecordDataCollector($input, $io)` is hard coded → you cannot inject a mock collector.
* `Domain::` static calls → need Mockery alias or a wrapper.
* `Helpers::createEntityManager()` is static.

If you share the `CloneRecordDataCollector` and `BaseCommand` code I can give you a fully working test with a partial mock of the command that overrides the collector creation, and a proper Mockery setup for the Domain static façade that is already used in your `RegisterConnectionCommandTest` / `ListTableSizesCommandTest`.
