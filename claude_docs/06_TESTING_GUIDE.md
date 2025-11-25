# Testing Guide

## Current Test Setup

### Backend Tests (PHPUnit)

**Location**: `backend/tests/`

**Structure**:
```
tests/
├── Unit/                    # Unit tests (no database)
│   ├── Account/
│   │   └── Service/
│   │       ├── AccountServiceTest.php
│   │       └── AccountSharingServiceTest.php
│   ├── Budget/
│   │   └── Service/
│   │       ├── ActiveBudgetServiceTest.php
│   │       └── BudgetInsightsServiceTest.php
│   ├── Money/
│   │   └── MoneyFactoryTest.php
│   └── Transaction/
│       └── Service/
│           └── TransactionServiceTest.php
├── Integration/             # Integration tests (with database)
│   ├── Api/
│   │   ├── AccountManagementTest.php
│   │   ├── CategoryManagementTest.php
│   │   ├── SavingsAccountManagementTest.php
│   │   ├── TransactionImportTest.php
│   │   └── TransactionManagementTest.php
│   └── Repository/
│       └── TransactionRepositoryTest.php
├── TestCase/                # Base test classes
│   ├── ApiTestCase.php
│   ├── DatabaseTestCase.php
│   └── WebTestCase.php
├── Fixtures/                # Test data generators
│   ├── CsvTestFixtures.php
│   └── TestFixtures.php
└── bootstrap.php            # Test bootstrap
```

### Running Tests

```bash
# Inside backend container
docker exec -it money-backend bash

# Run all tests
php bin/phpunit

# Run with verbose output
php bin/phpunit --testdox

# Run specific test file
php bin/phpunit tests/Unit/Account/Service/AccountServiceTest.php

# Run specific test method
php bin/phpunit --filter testAccountCreation

# Run only unit tests
php bin/phpunit tests/Unit/

# Run only integration tests
php bin/phpunit tests/Integration/
```

### Test Configuration

**phpunit.xml.dist**:
```xml
<php>
    <server name="APP_ENV" value="test" force="true" />
    <env name="DATABASE_URL" value="mysql://money:password@money-mysql:3306/money_test" />
</php>
```

### Test Database

Tests use a separate database `money_test`. The Symfony test environment appends `_test` suffix automatically.

```bash
# Create test database
php bin/console doctrine:database:create --env=test

# Run migrations on test database
php bin/console doctrine:migrations:migrate --env=test
```

## Frontend Testing

### Current State
**No frontend tests are currently implemented.**

The frontend uses:
- React 19
- TypeScript
- Vite

### Recommended Testing Stack

```bash
# Install testing dependencies
npm install -D vitest @testing-library/react @testing-library/jest-dom jsdom
```

**vitest.config.ts**:
```typescript
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/test/setup.ts',
  },
});
```

**package.json** scripts:
```json
{
  "scripts": {
    "test": "vitest",
    "test:run": "vitest run",
    "test:coverage": "vitest run --coverage"
  }
}
```

## Test Categories

### Unit Tests
Test individual classes in isolation with mocked dependencies.

**Example** (`AccountServiceTest.php`):
```php
class AccountServiceTest extends TestCase
{
    private AccountService $service;
    private MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AccountRepository::class);
        $this->service = new AccountService($this->repository);
    }

    public function testCreateAccount(): void
    {
        // Arrange
        $user = new User();
        $iban = 'NL91ABNA0417164300';

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Account::class));

        // Act
        $account = $this->service->create($user, $iban);

        // Assert
        $this->assertEquals($iban, $account->getAccountNumber());
    }
}
```

### Integration Tests
Test API endpoints with real database.

**Example** (`AccountManagementTest.php`):
```php
class AccountManagementTest extends ApiTestCase
{
    public function testListAccounts(): void
    {
        // Arrange - create test user and account
        $user = $this->createUser();
        $this->createAccount($user, 'NL91ABNA0417164300');

        // Act
        $this->client->request('GET', '/api/accounts', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getToken($user)
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
    }
}
```

## Test Coverage

### Checking Coverage
```bash
# Generate coverage report (requires Xdebug or PCOV)
php bin/phpunit --coverage-html var/coverage

# View coverage in browser
open var/coverage/index.html
```

### Current Coverage Areas

| Domain | Unit Tests | Integration Tests |
|--------|------------|-------------------|
| Account | Yes | Yes |
| Transaction | Yes | Yes |
| Budget | Yes | No |
| Category | No | Yes |
| Pattern | No | No |
| SavingsAccount | No | Yes |
| Security | No | No |

## Missing Test Coverage

### High Priority
1. **Pattern Service**: Critical for auto-categorization
2. **Security/Auth**: Login, JWT, rate limiting
3. **Budget Calculations**: Financial calculations need accuracy

### Medium Priority
1. **Category Service**: Hierarchy handling
2. **Transaction Import**: CSV parsing edge cases
3. **AI Suggestions**: OpenAI integration

## Test Fixtures

### TestFixtures.php
```php
class TestFixtures
{
    public static function createUser(string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('$2y$...');  // Pre-hashed
        return $user;
    }

    public static function createAccount(User $user, string $iban): Account
    {
        $account = new Account();
        $account->setAccountNumber($iban);
        $account->addOwner($user);
        return $account;
    }

    public static function createTransaction(Account $account, int $amount): Transaction
    {
        $transaction = new Transaction();
        $transaction->setAccount($account);
        $transaction->setAmount($amount);
        $transaction->setCurrency('EUR');
        $transaction->setDate(new \DateTime());
        return $transaction;
    }
}
```

### CsvTestFixtures.php
Provides sample CSV data for import testing.

## CI Integration

Currently **not integrated** into CI/CD pipeline.

### Recommended Addition to Workflows

```yaml
- name: Run backend tests
  run: |
    docker exec $CONTAINER php bin/console doctrine:database:create --env=test --if-not-exists
    docker exec $CONTAINER php bin/console doctrine:migrations:migrate --env=test --no-interaction
    docker exec $CONTAINER php bin/phpunit --testdox
```

## Writing New Tests

### Test Naming Convention
```
test[MethodName][Scenario][ExpectedResult]

# Examples:
testCreateAccountWithValidIban
testCreateAccountWithDuplicateIbanThrowsException
testGetBalanceReturnsZeroForEmptyAccount
```

### Test Structure (AAA Pattern)
```php
public function testExample(): void
{
    // Arrange - Set up test data
    $user = TestFixtures::createUser();

    // Act - Execute the code being tested
    $result = $this->service->doSomething($user);

    // Assert - Verify the outcome
    $this->assertEquals('expected', $result);
}
```
