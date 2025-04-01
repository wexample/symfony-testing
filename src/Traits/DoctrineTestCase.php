<?php

namespace Wexample\SymfonyTesting\Traits;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Interfaces\AbstractEntityInterface;
use Wexample\Helpers\Helper\ClassHelper;

trait DoctrineTestCase
{
    private array $startingIds = [];

    private array $entityCounters = [];

    public function entitySave($entity): AbstractEntityInterface
    {
        $manager = $this->getEntityManager();

        try {
            $manager->persist($entity);
            $manager->flush();

            $this->getEntityManager()->refresh($entity);

            $this->logSecondary(
                'Created entity '
                .ClassHelper::getTableizedName($entity)
                .' #'.$entity->getId()
            );
        } catch (\Exception $e) {
            $this->error('Unable to persist entity '.$entity::class.' : '.$e->getMessage());
        }

        return $entity;
    }

    public function getEntityManager(): EntityManager
    {
        // Allow overriding used entity manager.
        return $this->getDoctrine()->getManager();
    }

    public function getDoctrine(): Registry
    {
        return self::getContainer()->get('doctrine');
    }

    public function entityRefresh(AbstractEntityInterface $entity): ?AbstractEntityInterface
    {
        return $this->getRepository($entity::class)->find($entity->getId());
    }

    /**
     * We should get repository from app container
     * to not have detached entities issues.
     */
    public function getRepository(string $className): EntityRepository
    {
        return $this->getEntityManager()->getRepository($className);
    }

    public function cleanupFromStartingId(
        string $tableName,
        int $startingId = null
    ): void {
        if (is_null($startingId)) {
            $startingId = $this->buildTestingTableIncrement($tableName);
        }

        $this->log('Cleaning up data from table "'.$tableName.'", starting at '.$startingId);
        $conn = $this->getEntityManager()->getConnection();
        $conn->executeQuery("DELETE FROM $tableName WHERE id >= $startingId");
    }

    public function buildTestingTableIncrement(string $tableName): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $lastId = (int) $conn->fetchOne("SELECT MAX(id) FROM $tableName");
        return ceil($lastId / 10000) * 10000;
    }

    public function setTestingDatabaseIncrement(string $tableName): int
    {
        $newId = $this->buildTestingTableIncrement($tableName);

        $this->log('Setting increment for table "'.$tableName.'" to '.$newId);

        $conn = $this->getEntityManager()->getConnection();
        $conn->executeQuery("ALTER TABLE $tableName AUTO_INCREMENT = $newId");

        $this->startingIds[$tableName] = $newId;

        return $newId;
    }

    public function setEntityCounter(string $entityType): int
    {
        $this->entityCounters[$entityType] = $this->countEntities($entityType);

        return $this->entityCounters[$entityType];
    }

    public function countEntities(string $entityType): int
    {
        return $this->getRepository($entityType)->count([]);
    }

    public function assertEntityCounterIncreased(
        string $entityType,
        int $increased = 1,
        string $message = null
    ): void {
        $this->assertEntityCounterEquals(
            $entityType,
            $this->entityCounters[$entityType] + $increased,
            $message
        );
    }

    public function assertEntityCounterEquals(
        string $entityType,
        int $equals = null,
        string $message = null
    ): void {
        if (is_null($equals)) {
            $equals = $this->entityCounters[$entityType];
            $messageEnd = 'has not changed';
        } else {
            $messageEnd = 'is equal to '.$equals;
        }

        $this->assertEquals(
            $equals,
            $this->countEntities($entityType),
            $message.' Entity '.$entityType.' count '.$messageEnd
        );
    }
}
