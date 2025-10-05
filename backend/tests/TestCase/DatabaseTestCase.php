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
    }

    protected function cleanDatabase(): void
    {
        // Get all table names
        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        // Disable foreign key checks
        $connection->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Truncate all tables
        foreach ($tables as $table) {
            if ($table !== 'doctrine_migration_versions') {
                $connection->exec("TRUNCATE TABLE `$table`");
            }
        }

        // Re-enable foreign key checks
        $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}