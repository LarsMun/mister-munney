#!/bin/bash

# API Test Case Base Class
cat > backend/tests/TestCase/ApiTestCase.php << 'EOF'
<?php

namespace App\Tests\TestCase;

use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class ApiTestCase extends WebTestCase
{
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

        $this->client->request(
            'POST',
            $uri,
            $additionalData,
            [$fieldName => $file]
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
EOF

echo "✅ ApiTestCase.php created"

# Create Integration/Api directory
mkdir -p backend/tests/Integration/Api

echo "✅ Integration/Api directory created"
echo ""
echo "🎉 Done! Now you can manually create the test files or I can provide them again."