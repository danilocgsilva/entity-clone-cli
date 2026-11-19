<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use Danilocgsilva\EntityCloneCli\Command\CreateMissingDatabaseCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CreateMissingDatabaseCommandTest extends TestCase
{
    private CreateMissingDatabaseCommand $command;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->command = new CreateMissingDatabaseCommand();
        $this->tester = new CommandTester($this->command);
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertSame('app:create-missing-database', $this->command->getName());
        $this->assertSame(
            'List databases present in the first connection but missing in the second, and optionally create them.',
            $this->command->getDescription()
        );
    }

    public function testConfigureAddsConnectionOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('connection-id-1'));
        $this->assertTrue($definition->hasOption('connection-id-2'));
    }

    public function testExecuteReturnsFailureWhenDatabaseIsNotConfigured(): void
    {
        // CreateMissingDatabaseCommand builds its dependencies directly:
        // CreateMissingDatabaseDataCollector (which calls Helpers::createEntityManager()
        // in its BaseDataCollector constructor) and later DatabaseWorks (a real PDO).
        // Without injection seams in the command, the only deterministic, DB-free
        // outcome is the error path: when no database is configured, the entity
        // manager factory throws a RuntimeException ("Database name is not set")
        // during the data collector construction, the command's try/catch returns
        // FAILURE. Clear the DB env vars here to make that path deterministic
        // regardless of the surrounding environment.
        //
        // Unlike the sibling compare/create commands whose error labels are
        // distinctive (e.g. "Error comparing databases"), this command's catch
        // block uses the generic "Error:" label, so the full expected message is
        // asserted to keep the check meaningful.
        $this->withDatabaseEnvCleared(function (): void {
            $this->tester->execute([
                '--connection-id-1' => '1',
                '--connection-id-2' => '2',
            ]);
        });

        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $this->assertStringContainsString('Missing Databases', $this->tester->getDisplay());
        $this->assertStringContainsString('Error: Database name is not set', $this->tester->getDisplay());
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
