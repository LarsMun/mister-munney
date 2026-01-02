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
        // Skip if no MySQL database available (e.g., in CI with SQLite)
        $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? '';
        if (!str_contains($databaseUrl, 'mysql://') || str_contains($databaseUrl, 'sqlite')) {
            $this->markTestSkipped('Database tests require MySQL connection');
        }

        $kernel = self::bootKernel();
        $this->container = static::getContainer();

        try {
            $this->entityManager = $this->container->get('doctrine')->getManager();
            // Test connection
            $this->entityManager->getConnection()->connect();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }

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