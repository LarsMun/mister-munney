<?php

namespace App\Tests\TestCase;

use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;
    protected ContainerInterface $container;
    protected ?User $currentUser = null;
    protected ?string $authToken = null;

    protected function setUp(): void
    {
        // Create client for API tests
        $this->client = static::createClient();

        // Get container and entity manager
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

    /**
     * Create a test user and authenticate
     */
    protected function authenticateAsUser(string $email = 'test@example.com', string $password = 'password'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));
        $user->setRoles(['ROLE_USER']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->currentUser = $user;
        $this->authToken = $this->generateJwtToken($user);

        return $user;
    }

    /**
     * Generate JWT token for a user
     */
    protected function generateJwtToken(User $user): string
    {
        $jwtManager = $this->container->get(JWTTokenManagerInterface::class);
        return $jwtManager->create($user);
    }

    /**
     * Clear authentication
     */
    protected function clearAuthentication(): void
    {
        $this->currentUser = null;
        $this->authToken = null;
    }

    /**
     * Make a JSON request
     */
    protected function makeJsonRequest(string $method, string $uri, array $data = []): void
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        // Add JWT token if authenticated
        if ($this->authToken) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->authToken;
        }

        $this->client->request(
            $method,
            $uri,
            [],
            [],
            $headers,
            json_encode($data)
        );
    }

    /**
     * Upload a file via multipart/form-data
     */
    protected function uploadFile(string $uri, string $fieldName, string $filePath, array $additionalData = []): void
    {
        $file = new UploadedFile(
            $filePath,
            basename($filePath),
            'text/csv',
            null,
            true
        );

        $headers = [];

        // Add JWT token if authenticated
        if ($this->authToken) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->authToken;
        }

        $this->client->request(
            'POST',
            $uri,
            $additionalData,
            [$fieldName => $file],
            $headers
        );
    }

    protected function assertJsonResponse(int $expectedStatusCode = 200): array
    {
        $response = $this->client->getResponse();

        $this->assertEquals(
            $expectedStatusCode,
            $response->getStatusCode(),
            "Expected status code {$expectedStatusCode}, got {$response->getStatusCode()}. Response: {$response->getContent()}"
        );

        $this->assertTrue(
            $response->headers->contains('Content-Type', 'application/json'),
            'Response is not JSON'
        );

        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content, 'Response body is not valid JSON');

        return $content;
    }

    protected function assertResponseHasKeys(array $keys, array $data = null): void
    {
        if ($data === null) {
            $data = json_decode($this->client->getResponse()->getContent(), true);
        }

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $data, "Response missing key: {$key}");
        }
    }

    protected function assertArrayItemsHaveKeys(array $keys, array $items): void
    {
        $this->assertNotEmpty($items, 'Array is empty');

        foreach ($items as $index => $item) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $item,
                    "Item at index {$index} missing key: {$key}"
                );
            }
        }
    }

    protected function getJsonResponseBody(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true);
    }

    protected function createTempCsvFile(array $rows): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_csv_');
        $handle = fopen($tmpFile, 'w');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
        return $tmpFile;
    }

    protected function cleanupTempFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
