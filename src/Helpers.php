<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli;

use Danilocgsilva\EntityClone\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

class Helpers
{
    public static function createEntityManager(): EntityManagerInterface
    {
        return EntityManagerFactory::create(
            projectRoot: __DIR__ . '/..',
            entityPaths: [__DIR__ . '/../src/Entities'],
        );
    }
}
