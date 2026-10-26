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

class RegisterConnectionCommandTest extends TestCase
{
    public function testExecuteSuccess(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
            
        $entityManager->expects($this->once())
            ->method('persist');
            
        $entityManager->expects($this->once())
            ->method('flush');
        
        $databaseAccess = new DatabaseAccess();
        $databaseAccess->setId(1);
        $repository->expects($this->once())
            ->method('persist');
        
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        $questionHelper = $this->createMock(QuestionHelper::class);
        
        // Mock the helper to return our test values
        $questionHelper->expects($this->exactly(6))
            ->method('ask')
            ->withConsecutive(
                [$this->isInstanceOf(Question::class), $input, $output],
                [$this->isInstanceOf(Question::class), $input, $output],
                [$this->isInstanceOf(Question::class), $input, $output],
                [$this->isInstanceOf(Question::class), $input, $output],
                [$this->isInstanceOf(Question::class), $input, $output],
                [$this->isInstanceOf(Question::class), $input, $output]
            )
            ->willReturnOnConsecutiveCalls(
                'Test Connection',
                'localhost',
                'testuser',
                'testdb',
                'testpass',
                '3306'
            );
        
        // Mock Helpers::createEntityManager() to return our mock
        $this->mockStaticMethod(Helpers::class, 'createEntityManager', $entityManager);
        
        $command = new RegisterConnectionCommand();
        
        // Set the helper on the command
        $command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet([
            'question' => $questionHelper
        ]));
        
        $result = $command->run([$input, $output], $io);
        
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
        
        $result = $command->run([$input, $output], $io);
        
        $this->assertEquals(1, $result);
    }
    
    private function mockStaticMethod(string $class, string $method, mixed $returnValue): void
    {
        $reflection = new \ReflectionClass($class);
        $originalMethod = $reflection->getMethod($method);
        $originalMethod->setAccessible(true);
        
        $mock = $this->createMock($class);
        $originalMethod->invoke(null);
    }
}
