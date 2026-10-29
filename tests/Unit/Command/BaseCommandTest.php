<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityCloneCli\Command\BaseCommand;

class BaseCommandTest extends TestCase
{
    protected function getCommandInstance(?EntityManagerInterface $entityManager = null): BaseCommand
    {
        return new class($entityManager) extends BaseCommand {
            public function __construct(private ?EntityManagerInterface $entityManager)
            {
                parent::__construct();
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            protected function createEntityManager(): EntityManagerInterface
            {
                return $this->entityManager ?? parent::createEntityManager();
            }
        };
    }

    protected function callProtectedMethod(BaseCommand $command, string $method, array $arguments): mixed
    {
        $reflection = new \ReflectionClass($command);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);
        return $methodReflection->invokeArgs($command, $arguments);
    }

    public function testRequireOptionWithProvidedValue(): void
    {
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        
        $input->expects($this->once())
            ->method('getOption')
            ->with('test-option')
            ->willReturn('provided-value');
        
        $command = $this->getCommandInstance();
        
        $result = $this->callProtectedMethod($command, 'requireOption', [$input, $io, 'test-option', 'Test question']);
        $this->assertEquals('provided-value', $result);
    }

    public function testRequireOptionByNumberWithValidSelection(): void
    {
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        
        $input->expects($this->once())
            ->method('getOption')
            ->with('test-option')
            ->willReturn('option2');
        
        $io->expects($this->never())
            ->method('ask');
        
        $command = $this->getCommandInstance();
        
        $result = $this->callProtectedMethod($command, 'requireOptionByNumber', [
            $input, 
            $io, 
            'test-option', 
            'Pick an option:', 
            ['option1', 'option2', 'option3']
        ]);
        
        $this->assertEquals('option2', $result);
    }

    public function testRequireOptionByNumberWithInvalidSelection(): void
    {
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        
        $input->expects($this->once())
            ->method('getOption')
            ->with('test-option')
            ->willReturn('invalid-option');
        
        $io->expects($this->never())
            ->method('ask');
        
        $command = $this->getCommandInstance();
        
        $result = $this->callProtectedMethod($command, 'requireOptionByNumber', [
            $input, 
            $io, 
            'test-option', 
            'Pick an option:', 
            ['option1', 'option2']
        ]);
        
        $this->assertNull($result);
    }

    public function testAskConnectionIdWithProvidedValue(): void
    {
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        
        $input->expects($this->once())
            ->method('getOption')
            ->with('connection-id')
            ->willReturn('1');
            
        $command = $this->getCommandInstance();
        
        $result = $this->callProtectedMethod($command, 'askConnectionId', [$input, $io, $entityManager]);
        $this->assertEquals(1, $result);
    }

    public function testResolveTwoConnectionsSuccess(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $databaseAccess1 = new DatabaseAccess();
        $databaseAccess1->setId(1);
        $databaseAccess1->setName('Connection 1');
        
        $databaseAccess2 = new DatabaseAccess();
        $databaseAccess2->setId(2);
        $databaseAccess2->setName('Connection 2');
        
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        
        $input->expects($this->exactly(2))
            ->method('getOption')
            ->willReturnCallback(function (string $option): string {
                if ($option === 'connection-id-1') return '1';
                if ($option === 'connection-id-2') return '2';
                return '';
            });
            
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
            
        $repository->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(function (int $id) use ($databaseAccess1, $databaseAccess2): ?DatabaseAccess {
                if ($id === 1) return $databaseAccess1;
                if ($id === 2) return $databaseAccess2;
                return null;
            });

        $command = $this->getCommandInstance($entityManager);
        
        $result = $this->callProtectedMethod($command, 'resolveTwoConnections', [$input, $io]);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals($databaseAccess1, $result[0]);
        $this->assertEquals($databaseAccess2, $result[1]);
    }
}
