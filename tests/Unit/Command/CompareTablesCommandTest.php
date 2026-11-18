<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use Danilocgsilva\EntityCloneCli\Command\CompareTablesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CompareTablesCommandTest extends TestCase
{
    private CompareTablesCommand $command;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->command = new CompareTablesCommand();
        $this->tester = new CommandTester($this->command);
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertSame('anatomy:compare-tables', $this->command->getName());
        $this->assertSame(
            'Compare tables between two database connections.',
            $this->command->getDescription()
        );
    }

    public function testConfigureAddsConnectionOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('connection-id-1'));
        $this->assertTrue($definition->hasOption('connection-id-2'));
    }

    public function testConfigureAddsConnectionOptionShortcuts(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertSame('c1', $definition->getOption('connection-id-1')->getShortcut());
        $this->assertSame('c2', $definition->getOption('connection-id-2')->getShortcut());
    }

    public function testExecuteReturnsFailureWhenDatabaseIsNotConfigured(): void
    {
        // CompareTablesCommand builds its dependencies directly:
        // CompareTablesDataCollector (which calls Helpers::createEntityManager()
        // in its BaseDataCollector constructor) and later DatabaseWorks / Domain
        // (real PDO). Without injection seams in the command, the only
        // deterministic, DB-free outcome is the error path: when no database is
        // configured, Helpers::createEntityManager() throws during the data
        // collector construction and the command's try/catch returns FAILURE.
        // Clear the DB env vars here to make that path deterministic regardless
        // of the surrounding environment.
        $this->withDatabaseEnvCleared(function (): void {
            $this->tester->execute([
                '--connection-id-1' => '1',
                '--connection-id-2' => '2',
            ]);
        });

        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $this->assertStringContainsString('Database Tables Comparison', $this->tester->getDisplay());
        $this->assertStringContainsString('Error comparing tables', $this->tester->getDisplay());
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
