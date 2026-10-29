<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityCloneCli\Command\RegisterConnectionCommand;

class RegisterConnectionCommandTest extends TestCase
{
    private RegisterConnectionCommand $command;
    private EntityManagerInterface $entityManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->command = new class($this->entityManager) extends RegisterConnectionCommand {
            public function __construct(private EntityManagerInterface $entityManager)
            {
                parent::__construct();
            }

            protected function createEntityManager(): EntityManagerInterface
            {
                return $this->entityManager;
            }
        };
    }

    public function testExecuteSuccess(): void
    {
        $persistedConnection = null;
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (object $connection) use (&$persistedConnection): bool {
                if (!$connection instanceof DatabaseAccess) {
                    return false;
                }

                $persistedConnection = $connection;
                $connection->setId(123);
                return true;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $input = $this->createMock(InputInterface::class);
        $output = new BufferedOutput();
        
        $questionHelper = $this->createMock(QuestionHelper::class);
        
        $answers = [
            'Test Connection',
            'localhost',
            'testuser',
            'testdb',
            'testpass',
            '3306'
        ];
        
        $callIndex = 0;
        
        $questionHelper->expects($this->exactly(6))
            ->method('ask')
            ->willReturnCallback(function (InputInterface $input, BufferedOutput $output, Question $question) use (&$callIndex, $answers): string {
                $answer = $answers[$callIndex] ?? 'Default';
                $callIndex++;
                return $answer;
            });

        $this->command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet([
            'question' => $questionHelper
        ]));
        
        $result = $this->command->run($input, $output); 
        
        $this->assertSame(Command::SUCCESS, $result);
        $this->assertInstanceOf(DatabaseAccess::class, $persistedConnection);
        $this->assertSame('Test Connection', $persistedConnection->getName());
        $this->assertSame('localhost', $persistedConnection->getHost());
        $this->assertSame('testuser', $persistedConnection->getUser());
        $this->assertSame('testpass', $persistedConnection->getPassword());
        $this->assertSame('testdb', $persistedConnection->getDatabaseName());
        $this->assertSame(3306, $persistedConnection->getPort());
    }

    public function testExecuteWithEmptyName(): void
    {
        $input = $this->createMock(InputInterface::class);
        $output = new BufferedOutput();
        
        $questionHelper = $this->createMock(QuestionHelper::class);
        
        $questionHelper->expects($this->once())
            ->method('ask')
            ->willReturnCallback(function (InputInterface $input, OutputInterface $output, Question $question): string {
                if (str_contains($question->getQuestion(), 'name')) {
                    return '';
                }
                return 'Test Connection';
            });

        $this->command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet([
            'question' => $questionHelper
        ]));
        
        $result = $this->command->run($input, $output); 
        
        $this->assertSame(Command::FAILURE, $result);
    }
}
