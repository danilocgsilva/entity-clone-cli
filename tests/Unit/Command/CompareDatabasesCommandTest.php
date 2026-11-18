<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use Danilocgsilva\EntityCloneCli\Command\CompareDatabasesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CompareDatabasesCommandTest extends TestCase
{
    private CompareDatabasesCommand $command;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->command = new CompareDatabasesCommand();
        $this->tester = new CommandTester($this->command);
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertSame('anatomy:compare-databases', $this->command->getName());
        $this->assertSame(
            'Compare the list of databases between two connections.',
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
        // CompareDatabasesCommand builds its dependencies directly:
        // CompareDatabasesDataCollector (which calls Helpers::createEntityManager())
        // and DatabaseWorks (a real PDO). Without injection seams in the command,
        // the only deterministic, DB-free outcome is the error path: when no
        // database is configured, Helpers::createEntityManager() throws and the
        // command's try/catch returns FAILURE. Clear the DB env vars here to make
        // that path deterministic regardless of the surrounding environment.
        $this->withDatabaseEnvCleared(function (): void {
            $this->tester->execute([
                '--connection-id-1' => '1',
                '--connection-id-2' => '2',
            ]);
        });

        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $this->assertStringContainsString('Databases Comparison', $this->tester->getDisplay());
        $this->assertStringContainsString('Error comparing databases', $this->tester->getDisplay());
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
