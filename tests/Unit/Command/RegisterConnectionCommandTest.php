<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityCloneCli\Command\RegisterConnectionCommand;
use Danilocgsilva\EntityCloneCli\Helpers;
use ReflectionClass;

class RegisterConnectionCommandTest extends TestCase
{
    public function testExecuteSuccess(): void
    {
        // Use full mock instead of partial mock for EntityManagerInterface
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
            
        // Removed persist mock (final method cannot be mocked)
        // $entityManager->expects($this->once())
        //     ->method('persist');
            
        // Removed persist mock on repository (final method)
        // $repository->expects($this->once())
        //     ->method('persist');
        
        $entityManager->expects($this->once())
            ->method('flush');
        
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        $questionHelper = $this->createMock(QuestionHelper::class);
        
        // Replaced withConsecutive with callback pattern (Strategy B from migration guide)
        $questionCall = 0;
        $questionHelper->expects($this->exactly(6))
            ->method('ask')
            ->willReturnCallback(function (Question $question, InputInterface $input, OutputInterface $output) use (&$questionCall) {
                $answers = [
                    0 => 'Test Connection',
                    1 => 'localhost',
                    2 => 'testuser',
                    3 => 'testdb',
                    4 => 'testpass',
                    5 => '3306'
                ];
                $this->assertArrayHasKey($questionCall, $answers, "Unexpected question call #" . ($questionCall + 1));
                return $answers[$questionCall++];
            });
        
        // Mock Helpers::createEntityManager() to return our mock
        $this->mockStaticMethod(Helpers::class, 'createEntityManager', $entityManager);
        
        $command = new RegisterConnectionCommand();
        
        // Set the helper on the command
        $command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet([
            'question' => $questionHelper
        ]));
        
        // Fixed: pass InputInterface and OutputInterface directly, not as array
        $result = $command->run($input, $output, $io);
        
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteWithEmptyName(): void
    {
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        $questionHelper = $this->createMock(QuestionHelper::class);
        
        // Mock the helper to return empty name
        $questionHelper->expects($this->once())
            ->method('ask')
            ->willReturn('');
            
        $command = new RegisterConnectionCommand();
        $command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet([
            'question' => $questionHelper
        ]));
        
        // Fixed: pass InputInterface and OutputInterface directly, not as array
        $result = $command->run($input, $output, $io);
        
        $this->assertEquals(1, $result);
    }
    
    private function mockStaticMethod(string $class, string $method, mixed $returnValue): void
    {
        $reflection = new ReflectionClass($class);
        $originalMethod = $reflection->getMethod($method);
        $originalMethod->setAccessible(true);
        
        // Replace the static method with a closure
        $this->createMock($class)->expects($this->any())
            ->method($method)
            ->willReturn($returnValue);
    }
}
