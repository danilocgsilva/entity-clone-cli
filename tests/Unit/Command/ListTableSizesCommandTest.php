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
use Danilocgsilva\EntityCloneCli\Command\ListTableSizesCommand;
use Danilocgsilva\EntityCloneCli\DatabaseConnectionLister;

class ListTableSizesCommandTest extends TestCase
{
    private DatabaseConnectionLister $connectionLister;
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;
    private ListTableSizesCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->connectionLister = $this->createMock(DatabaseConnectionLister::class);

        $em = $this->entityManager;

        $this->command = new class($this->connectionLister, $em) extends ListTableSizesCommand {
            public function __construct(
                DatabaseConnectionLister $connectionLister,
                private EntityManagerInterface $em
            ) {
                parent::__construct($connectionLister);
            }

            protected function createEntityManager(): EntityManagerInterface
            {
                return $this->em;
            }
        };
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

        $this->command->setHelperSet(new HelperSet(['question' => new QuestionHelper()]));

        $input = new ArrayInput(['--connection-id' => '99'], $this->command->getDefinition());

        $result = $this->command->run($input, new BufferedOutput());

        $this->assertSame(Command::FAILURE, $result);
    }

    public function testFormatFileSizeBytes(): void
    {
        $method = new \ReflectionMethod(ListTableSizesCommand::class, 'formatFileSize');
        $this->assertSame('512 bytes', $method->invoke($this->command, 512.0));
    }

    public function testFormatFileSizeKilobytes(): void
    {
        $method = new \ReflectionMethod(ListTableSizesCommand::class, 'formatFileSize');
        $this->assertSame('1.50 KB', $method->invoke($this->command, 1536.0));
    }

    public function testFormatFileSizeMegabytes(): void
    {
        $method = new \ReflectionMethod(ListTableSizesCommand::class, 'formatFileSize');
        $this->assertSame('1.50 MB', $method->invoke($this->command, 1572864.0));
    }

    public function testFormatFileSizeGigabytes(): void
    {
        $method = new \ReflectionMethod(ListTableSizesCommand::class, 'formatFileSize');
        $this->assertSame('1.50 GB', $method->invoke($this->command, 1610612736.0));
    }
}
