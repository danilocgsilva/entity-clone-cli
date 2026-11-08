<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Command\Command;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Danilocgsilva\EntityClone\Entities\DatabaseAccess;
use Danilocgsilva\EntityCloneCli\Command\DeleteConnectionCommand;
use Danilocgsilva\EntityCloneCli\Command\DataCollectors\DeleteConnectionDataCollector;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;

class DeleteConnectionCommandTest extends TestCase
{
    private DatabaseConnectionLister $connectionLister;
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;
    private DeleteConnectionCommand $command;

    protected function setUp(): void
    {
        $this->connectionLister = $this->createMock(DatabaseConnectionLister::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);

        $em = $this->entityManager;

        $this->command = new class($this->connectionLister, $em) extends DeleteConnectionCommand {
            public function __construct(
                DatabaseConnectionLister $connectionLister,
                private EntityManagerInterface $em
            ) {
                parent::__construct($connectionLister);
            }

            protected function createDataCollector(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Style\SymfonyStyle $io
            ): DeleteConnectionDataCollector {
                $em = $this->em;
                return new class($input, $io, $em) extends DeleteConnectionDataCollector {
                    public function __construct(
                        \Symfony\Component\Console\Input\InputInterface $input,
                        \Symfony\Component\Console\Style\SymfonyStyle $io,
                        private EntityManagerInterface $injectedEm
                    ) {
                        parent::__construct($input, $io);
                        $this->entityManager = $this->injectedEm;
                    }
                };
            }
        };

        $questionHelper = $this->createMock(QuestionHelper::class);
        $questionHelper->method('ask')->willReturn(false);
        $this->command->setHelperSet(new HelperSet(['question' => $questionHelper]));
    }

    public function testExecuteFailsWhenConnectionIdNotFound(): void
    {
        $this->connectionLister->expects($this->once())->method('listConnections');

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($this->repository);

        $this->repository->expects($this->once())
            ->method('find')
            ->with(99)
            ->willReturn(null);

        $input = new ArrayInput(['--connection-id' => '99'], $this->command->getDefinition());

        $result = $this->command->run($input, new BufferedOutput());

        $this->assertSame(Command::FAILURE, $result);
    }

    public function testExecuteSuccessfullyDeletesConnection(): void
    {
        $databaseAccess = $this->createMock(DatabaseAccess::class);
        $databaseAccess->method('getName')->willReturn('My Connection');

        $this->connectionLister->expects($this->once())->method('listConnections');

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DatabaseAccess::class)
            ->willReturn($this->repository);

        $this->repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($databaseAccess);

        $this->entityManager->expects($this->once())->method('remove')->with($databaseAccess);
        $this->entityManager->expects($this->once())->method('flush');

        $questionHelper = $this->createMock(QuestionHelper::class);
        $questionHelper->method('ask')->willReturn(true);
        $this->command->setHelperSet(new HelperSet(['question' => $questionHelper]));

        $input = new ArrayInput(['--connection-id' => '1'], $this->command->getDefinition());

        $result = $this->command->run($input, new BufferedOutput());

        $this->assertSame(Command::SUCCESS, $result);
    }
}
