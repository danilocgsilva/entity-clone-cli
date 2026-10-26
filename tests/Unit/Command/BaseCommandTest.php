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
    public function testRequireOptionWithProvidedValue(): void
    {
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        
        $input->expects($this->once())
            ->method('getOption')
            ->with('test-option')
            ->willReturn('provided-value');
            
        $command = new class extends BaseCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };
        
        $result = $command->requireOption($input, $io, 'test-option', 'Test question');
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
            
        $options = ['option1', 'option2', 'option3'];
        
        $command = new class extends BaseCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };
        
        $result = $command->requireOptionByNumber($input, $io, 'test-option', 'Pick an option:', $options);
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
            
        $options = ['option1', 'option2'];
        
        $command = new class extends BaseCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };
        
        $result = $command->requireOptionByNumber($input, $io, 'test-option', 'Pick an option:', $options);
        $this->assertNull($result);
    }
    
    public function testAskConnectionIdWithProvidedValue(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $databaseAccess = new DatabaseAccess();
        $databaseAccess->setId(1);
        $databaseAccess->setName('Test Connection');
        
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        
        $input->expects($this->once())
            ->method('getOption')
            ->with('connection-id')
            ->willReturn('1');
            
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
            
        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($databaseAccess);
            
        $command = new class extends BaseCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };
        
        $result = $command->askConnectionId($input, $io, $entityManager);
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
            ->withConsecutive(['connection-id-1'], ['connection-id-2'])
            ->willReturnOnConsecutiveCalls('1', '2');
            
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
            
        $repository->expects($this->exactly(2))
            ->method('find')
            ->withConsecutive([1], [2])
            ->willReturnOnConsecutiveCalls($databaseAccess1, $databaseAccess2);
            
        $command = new class extends BaseCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };
        
        $result = $command->resolveTwoConnections($input, $io);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals($databaseAccess1, $result[0]);
        $this->assertEquals($databaseAccess2, $result[1]);
    }
}
