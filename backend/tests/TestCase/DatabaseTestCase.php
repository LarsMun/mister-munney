<?php

namespace App\Tests\TestCase;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;
    protected ContainerInterface $container;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->container = static::getContainer();
        $this->entityManager = $this->container->get('doctrine')->getManager();

        // Clean database before each test
        $this->cleanDatabase();

        // Clear entity manager to avoid cached entities
        $this->entityManager->clear();
    }

    protected function cleanDatabase(): void
    {
        $connection = $this->entityManager->getConnection();

        // Disable foreign key checks
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        // Get all table names
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        // Truncate all tables except migrations
        foreach ($tables as $table) {
            if ($table !== 'doctrine_migration_versions') {
                $connection->executeStatement("TRUNCATE TABLE `$table`");
            }
        }

        // Re-enable foreign key checks
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Properly close entity manager
        if ($this->entityManager && $this->entityManager->isOpen()) {
            $this->entityManager->close();
        }
    }
}