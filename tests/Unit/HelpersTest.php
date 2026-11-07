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
        putenv('APP_ENV=test');
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PORT=3306');
        putenv('DB_NAME_TEST=test_db');
        putenv('DB_USER=user');
        putenv('DB_PASSWORD=password');

        $result = Helpers::createEntityManager();

        $this->assertInstanceOf(EntityManagerInterface::class, $result);
    }
}