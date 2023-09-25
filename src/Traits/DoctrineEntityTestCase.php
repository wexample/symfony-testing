<?php

namespace Wexample\SymfonyTesting\Traits;

use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Helper\ClassHelper;

trait DoctrineEntityTestCase
{
    use DoctrineTestCase;

    public function resetEntityTestingDatabaseIncrement(
        string|AbstractEntity $entity
    ): int {
        $this->cleanupEntitiesFromStartingId(
            $entity
        );

        $id = $this->setEntityTestingDatabaseIncrement(
            $entity,
        );

        $this->setEntityCounter(
            $entity
        );

        return $id;
    }

    public function cleanupEntitiesFromStartingId(
        string|AbstractEntity $entity,
        int $startingId = null
    ): void {
        $this->cleanupFromStartingId(
            $this->getTableNameFromEntity($entity),
            $startingId
        );
    }

    private function getTableNameFromEntity(string $entityClass): string
    {
        return ClassHelper::getClassPath(
            $this
                ->getEntityManager()
                ->getClassMetadata($entityClass)
                ->getTableName()
        );
    }

    public function setEntityTestingDatabaseIncrement(
        string|AbstractEntity $entity
    ): int {
        return $this->setTestingDatabaseIncrement(
            $this->getTableNameFromEntity($entity),
        );
    }
}
