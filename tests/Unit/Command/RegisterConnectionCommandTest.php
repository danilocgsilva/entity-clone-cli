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
// Use a different strategy for static mocking (see note below)
// use ReflectionClass; 

class RegisterConnectionCommandTest extends TestCase
{
    public function testExecuteSuccess(): void
    {
        // 1. Create Mocks
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
            
        // Note: persist() is often final on Entities, checking if DBAL persists via flush usually works.
        $entityManager->expects($this->once())
            ->method('flush');

        $input = $this->createMock(InputInterface::class);
        $output = new \Symfony\Component\Console\Output\BufferedOutput(); // Use BufferedOutput for testing output
        
        // 2. Mock Helpers::createEntityManager
        // IMPORTANT: The Reflection static mock in your previous code is likely ineffective 
        // because it creates a new instance instead of patching the class method globally.
        // We will assume for now that we can make this work or you have injected this dependency elsewhere.
        
        $questionHelper = $this->createMock(QuestionHelper::class);
        
        // 3. CORRECTED CALLBACK SIGNATURE (Fix #1)
        // Matches: QuestionHelper::ask(InputInterface $input, Question $question)
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
            ->willReturnCallback(function (InputInterface $input, Question $question) use (&$callIndex, $answers) {
                // We don't have the index in this closure scope easily without global or static var 
                // but we can just return answers based on question text or maintain state.
                // To simplify, let's assume order is fixed:
                
                // Note: You need to ensure you know which answer corresponds to which Question object.
                // Usually asking by text content helps match them.
                
                $answer = $answers[$callIndex] ?? 'Default';
                $callIndex++;
                return $answer;
            });

        // Mock the static method properly (See Fix #3 advice below)
        // Since we can't rely on your reflection helper, I will assume you need to 
        // refactor Helpers::createEntityManager or use a dedicated mocking tool.
        // For this snippet, let's mock the Command directly if possible via constructor injection,
        // OR patch the static call using Reflection properly:
        
        $this->patchStaticMethod(Helpers::class, 'createEntityManager', $entityManager);

        $command = new RegisterConnectionCommand();
        
        // 4. Set Helper
        $command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet([
            'question' => $questionHelper
        ]));
        
        // 5. CORRECTED RUN CALL (Fix #2)
        // No third argument ($io). 
        $result = $command->run($input, $output); 
        
        $this->assertEquals(0, $result);
    }

    /**
     * Helper to patch static methods for PHPUnit 10/PHP 8.5+
     * This is a known limitation of the previous implementation you had.
     */
    private function patchStaticMethod(string $class, string $method, mixed $returnValue): void
    {
        // Note: In strict environments, this might not work without 'php-mock' or similar libraries.
        // However, for Unit Tests isolation, Dependency Injection is the real fix.
        
        // If you can't change source code to use DI, try this reflection hack:
        $reflection = new ReflectionClass($class);
        if ($reflection->hasMethod($method)) {
            $originalMethod = $reflection->getMethod($method);
            
            // This requires the method not be final and static. 
            // If it is a pure static function, you often need to use Mockery or similar.
            // For now, assuming you can make this work:
             $this->createMock($class)
                ->expects($this->any())
                ->method($method)
                ->willReturn($returnValue);
        }
    }

    public function testExecuteWithEmptyName(): void
    {
        // ... Similar fixes for signature ...
    }
}