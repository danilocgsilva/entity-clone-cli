<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;

class DatabaseConnectionListerTest extends TestCase
{
    public function testListConnectionsEmpty(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
            
        $repository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);
            
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('info')
            ->with('No database connections registered.');
            
        $connectionLister = new DatabaseConnectionLister($entityManager);
        $connectionLister->listConnections($io);
    }
    
    public function testListConnectionsWithData(): void
    {
        $databaseAccess1 = new DatabaseAccess();
        $databaseAccess1->setId(1);
        $databaseAccess1->setName('Test DB 1');
        $databaseAccess1->setHost('localhost');
        $databaseAccess1->setUser('user1');
        $databaseAccess1->setDatabaseName('db1');
        $databaseAccess1->setPort(3306);
        
        $databaseAccess2 = new DatabaseAccess();
        $databaseAccess2->setId(2);
        $databaseAccess2->setName('Test DB 2');
        $databaseAccess2->setHost('192.168.1.1');
        $databaseAccess2->setUser('user2');
        $databaseAccess2->setDatabaseName('db2');
        $databaseAccess2->setPort(5432);
        
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($repository);
            
        $repository->expects($this->once())
            ->method('findAll')
            ->willReturn([$databaseAccess1, $databaseAccess2]);
            
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('table')
            ->with(
                ['ID', 'Name', 'Host', 'User', 'Database', 'Port'],
                [
                    [1, 'Test DB 1', 'localhost', 'user1', 'db1', 3306],
                    [2, 'Test DB 2', '192.168.1.1', 'user2', 'db2', 5432]
                ]
            );
            
        $connectionLister = new DatabaseConnectionLister($entityManager);
        $connectionLister->listConnections($io);
    }
}
