<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Danilocgsilva\EntityCloneCli\Helpers;
use Danilocgsilva\EntityClone\EntityManagerFactory;

class HelpersTest extends TestCase
{
    public function testCreateEntityManager(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        
        // Store original method
        $originalCreateEntityManager = [Helpers::class, 'createEntityManager'];
        
        // Use reflection to temporarily replace the method
        $reflection = new \ReflectionClass(Helpers::class);
        $method = $reflection->getMethod('createEntityManager');
        $method->setAccessible(true);
        
        $result = $method->invoke(null);
        
        $this->assertInstanceOf(EntityManagerInterface::class, $result);
    }
}