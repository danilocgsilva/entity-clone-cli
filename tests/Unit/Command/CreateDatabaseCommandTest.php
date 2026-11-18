<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use Danilocgsilva\EntityCloneCli\Command\CreateDatabaseCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CreateDatabaseCommandTest extends TestCase
{
    private CreateDatabaseCommand $command;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->command = new CreateDatabaseCommand();
        $this->tester = new CommandTester($this->command);
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertSame('app:create-database', $this->command->getName());
        $this->assertSame(
            'Create a new empty database using an existing connection.',
            $this->command->getDescription()
        );
    }

    public function testConfigureAddsOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('connection-id'));
        $this->assertTrue($definition->hasOption('database-name'));
    }

    public function testConfigureAddsOptionShortcuts(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertSame('c', $definition->getOption('connection-id')->getShortcut());
        $this->assertSame('n', $definition->getOption('database-name')->getShortcut());
    }

    public function testExecuteReturnsFailureWhenDatabaseIsNotConfigured(): void
    {
        // CreateDatabaseCommand builds its dependencies directly:
        // CreateDatabaseDataCollector (which calls Helpers::createEntityManager()
        // in its BaseDataCollector constructor) and later a real PDO connection.
        // Without injection seams in the command, the only deterministic, DB-free
        // outcome is the error path: when no database is configured, the entity
        // manager factory throws a RuntimeException ("Database name is not set")
        // during the data collector construction, the command's try/catch returns
        // FAILURE. Clear the DB env vars here to make that path deterministic
        // regardless of the surrounding environment.
        $this->withDatabaseEnvCleared(function (): void {
            $this->tester->execute([
                '--connection-id' => '1',
                '--database-name' => 'new_db',
            ]);
        });

        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $this->assertStringContainsString('Create New Database', $this->tester->getDisplay());
        $this->assertStringContainsString('Error creating database', $this->tester->getDisplay());
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
