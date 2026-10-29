<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;

class HelpersTest extends TestCase
{
    public function testCreateEntityManager(): void
    {
        $result = Helpers::createEntityManager();
        
        $this->assertInstanceOf(EntityManagerInterface::class, $result);
    }
}