<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use Danilocgsilva\EntityCloneCli\Command\CreateTableFromSourceCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CreateTableFromSourceCommandTest extends TestCase
{
    private CreateTableFromSourceCommand $command;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->command = new CreateTableFromSourceCommand();
        $this->tester = new CommandTester($this->command);
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertSame('app:table:create-from-source', $this->command->getName());
        $this->assertSame(
            'Create a table in target database from source database structure',
            $this->command->getDescription()
        );
    }

    public function testConfigureAddsOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('source-connection'));
        $this->assertTrue($definition->hasOption('target-connection'));
        $this->assertTrue($definition->hasOption('database-name'));
        $this->assertTrue($definition->hasOption('table-name'));
    }

    public function testConfigureAddsOptionShortcuts(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertSame('s', $definition->getOption('source-connection')->getShortcut());
        $this->assertSame('t', $definition->getOption('target-connection')->getShortcut());
        $this->assertSame('d', $definition->getOption('database-name')->getShortcut());
    }

    public function testExecuteReturnsFailureWhenDatabaseIsNotConfigured(): void
    {
        // CreateTableFromSourceCommand resolves its options through
        // initializeOptions() (BaseCommand::requireOption) before the try/catch,
        // then calls Helpers::createEntityManager() directly inside execute().
        // Without injection seams, the only deterministic, DB-free outcome is the
        // error path: when no database is configured, the entity manager factory
        // throws a RuntimeException ("Database name is not set") which the command's
        // generic catch (Exception) block turns into FAILURE. Supplying all four
        // options on the command line keeps initializeOptions() from prompting
        // interactively. Clear the DB env vars here to make that path deterministic
        // regardless of the surrounding environment.
        $this->withDatabaseEnvCleared(function (): void {
            $this->tester->execute([
                '--source-connection' => '1',
                '--target-connection' => '2',
                '--database-name' => 'some_db',
                '--table-name' => 'some_table',
            ]);
        });

        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $this->assertStringContainsString('Create Table from Source Database', $this->tester->getDisplay());
        $this->assertStringContainsString('Error creating table', $this->tester->getDisplay());
    }

    /**
     * Runs $callback with all database-related environment variables cleared,
     * restoring their previous values afterwards so other tests are unaffected.
     */
    private function withDatabaseEnvCleared(callable $callback): void
    {
        $keys = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_NAME_TEST', 'DB_USER', 'DB_PASSWORD', 'APP_ENV'];
        $backup = [];
        foreach ($keys as $key) {
            $backup[$key] = getenv($key);
            putenv($key);
        }

        try {
            $callback();
        } finally {
            foreach ($backup as $key => $value) {
                putenv($value === false ? $key : "$key=$value");
            }
        }
    }
}
