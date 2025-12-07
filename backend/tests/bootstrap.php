<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Only setup database for integration tests (skip for unit tests)
$isIntegrationTest = in_array('--testsuite=Integration', $_SERVER['argv'] ?? [], true)
    || (getenv('PHPUNIT_TESTSUITE') === 'Integration');

if ($isIntegrationTest) {
    // Ensure test database exists for integration tests
    $output = [];
    $returnCode = 0;
    exec('php bin/console doctrine:database:create --env=test --if-not-exists 2>&1', $output, $returnCode);

    if ($returnCode === 0) {
        exec('php bin/console doctrine:migrations:migrate --no-interaction --env=test 2>&1');
    } else {
        fwrite(STDERR, "Warning: Could not setup test database. Integration tests may fail.\n");
        fwrite(STDERR, implode("\n", $output) . "\n");
    }
}