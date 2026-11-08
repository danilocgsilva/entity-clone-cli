<?php

declare(strict_types=1);

namespace Danilocgsilva\EntityCloneCli\Command\DataCollectors;

use Danilocgsilva\EntityClone\Entities\DatabaseAccess;

class DeleteConnectionDataCollector extends BaseDataCollector
{
    public function collect(): ?array
    {
        $repository = $this->entityManager->getRepository(DatabaseAccess::class);

        $connectionId = (int) $this->requireOption('connection-id', 'Please enter the database connection ID to delete:');
        if (!$connectionId) {
            return null;
        }

        $databaseAccess = $repository->find($connectionId);
        if (!$databaseAccess) {
            $this->io->error("Database connection with ID {$connectionId} not found.");
            return null;
        }

        return ['databaseAccess' => $databaseAccess, 'entityManager' => $this->entityManager];
    }
}
