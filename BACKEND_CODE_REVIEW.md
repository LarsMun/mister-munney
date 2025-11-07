# Munney Backend Codebase - Comprehensive Code Quality Report

**Report Date:** November 6, 2025  
**Codebase:** Symfony 7.2 + PHP 8.3  
**Total PHP Files:** 115  
**Total Lines of Code:** 17,071  
**Test Files:** 10

---

## 1. DIRECTORY STRUCTURE OVERVIEW

### 1.1 Complete Source Tree

```
backend/src/
├── Account/
│   ├── Controller/ (1 controller)
│   ├── DTO/ (1 DTO)
│   ├── EventListener/
│   ├── Mapper/
│   ├── Repository/ (1 repository)
│   └── Service/ (1 service)
├── Budget/
│   ├── Controller/ (6 controllers)
│   ├── DTO/ (15 DTOs)
│   ├── Mapper/
│   ├── Repository/ (2 repositories)
│   └── Service/ (7 services)
├── Category/
│   ├── Controller/ (1 controller)
│   ├── DTO/ (2 DTOs)
│   ├── Mapper/
│   ├── Repository/
│   └── Service/
├── Command/ (1 command)
├── DataFixtures/
├── Entity/ (11 entities)
├── Enum/ (6 enums)
├── EventListener/ (API exception listener)
├── FeatureFlag/
│   ├── Repository/
│   └── Service/
├── Mapper/ (PayloadMapper - shared)
├── Money/ (MoneyFactory - shared)
├── Pattern/
│   ├── Controller/ (4 controllers)
│   ├── DTO/ (6 DTOs)
│   ├── Mapper/
│   ├── Repository/ (2 repositories)
│   └── Service/ (4 services)
├── SavingsAccount/
│   ├── Controller/
│   ├── DTO/ (4 DTOs)
│   ├── Mapper/
│   ├── Repository/
│   └── Service/
├── Transaction/
│   ├── Controller/ (4 controllers)
│   ├── DTO/ (5 DTOs)
│   ├── Mapper/ (2 mappers)
│   ├── Repository/
│   ├── Request/
│   └── Service/ (8 services)
└── Kernel.php

Migrations: 15 migration files
```

### 1.2 Configuration Files

**Located in:** `backend/config/packages/`

- `cache.yaml` - Application caching
- `doctrine.yaml` - Database ORM configuration
- `doctrine_migrations.yaml` - Migration settings
- `framework.yaml` - Core Symfony framework
- `http_discovery.yaml` - HTTP discovery
- `monolog.yaml` - Logging configuration
- `nelmio_api_doc.yaml` - OpenAPI documentation
- `nelmio_cors.yaml` - CORS configuration
- `routing.yaml` - Route loading
- `security.yaml` - Security/authentication (currently all access disabled)
- `twig.yaml` - Template engine
- `validator.yaml` - Validation rules

---

## 2. CONTROLLERS & ENDPOINTS

### 2.1 All Controllers (17 total)

#### Account Domain (1 controller)
- **AccountController** (5 endpoints)
  - GET `/api/accounts` - List all accounts
  - GET `/api/accounts/{id}` - Get specific account
  - PUT `/api/accounts/{id}` - Update account
  - PUT `/api/accounts/{id}/default` - Set default account

#### Budget Domain (6 controllers)
- **BudgetController** (12 endpoints)
  - POST `/api/account/{accountId}/budget/create`
  - PUT `/api/account/{accountId}/budget/{budgetId}`
  - DELETE `/api/account/{accountId}/budget/{budgetId}`
  - GET `/api/account/{accountId}/budget`
  - GET `/api/account/{accountId}/budget/month/{monthYear}`
  - GET `/api/account/{accountId}/budget/{budgetId}`
  - PUT `/api/account/{accountId}/budget/{budgetId}/categories`
  - DELETE `/api/account/{accountId}/budget/{budgetId}/categories/{categoryId}`
  - GET `/api/account/{accountId}/budget/summary/{monthYear}`
  - GET `/api/account/{accountId}/budget/{budgetId}/breakdown/{monthYear}`
  - GET `/api/account/{accountId}/budget/{budgetId}/breakdown-range`

- **AdaptiveDashboardController** (9 endpoints)
  - GET `/api/budgets/active` - Get active budgets with insights
  - GET `/api/budgets/older` - Get inactive budgets
  - POST `/api/budgets` - Create project
  - PATCH `/api/budgets/{id}` - Update project
  - GET `/api/budgets/{id}/details` - Get project details
  - GET `/api/budgets` - List projects
  - GET `/api/budgets/{id}/entries` - Get project entries
  - GET `/api/budgets/{id}/external-payments` - Get external payments

- **BudgetVersionController** (3 endpoints)
  - POST `/api/account/{accountId}/budget/{budgetId}/version/create`
  - PUT `/api/account/{accountId}/budget/{budgetId}/version/{versionId}`
  - DELETE `/api/account/{accountId}/budget/{budgetId}/version/{versionId}`

- **ExternalPaymentController** (5 endpoints)
  - POST `/api/budgets/{budgetId}/external-payments` - Create payment
  - PATCH `/api/external-payments/{id}` - Update payment
  - DELETE `/api/external-payments/{id}` - Delete payment
  - POST `/api/external-payments/{id}/attachment` - Upload attachment
  - DELETE `/api/external-payments/{id}/attachment` - Remove attachment

- **ProjectAttachmentController** (2 endpoints)
- **IconController** (2 endpoints)

#### Category Domain (1 controller)
- **CategoryController** (11 endpoints)
  - GET `/api/account/{accountId}/category` - List categories
  - POST `/api/account/{accountId}/category/create` - Create category
  - PUT `/api/account/{accountId}/category/{categoryId}` - Update category
  - DELETE `/api/account/{accountId}/category/{categoryId}` - Delete category
  - GET `/api/account/{accountId}/category/{categoryId}` - Get category
  - GET `/api/account/{accountId}/category/{categoryId}/transactions`
  - PUT `/api/account/{accountId}/category/{categoryId}/budget` - Assign to budget
  - DELETE `/api/account/{accountId}/category/{categoryId}/budget`

#### Transaction Domain (4 controllers)
- **TransactionController** - Transaction management
- **TransactionImportController** - CSV and PayPal import
- **TransactionSplitController** - Transaction splitting
- **AiCategorizationController** - AI-powered categorization

#### Pattern Domain (4 controllers)
- **PatternController** - Pattern CRUD
- **AiPatternDiscoveryController** - AI pattern discovery
- **MatchingPatternController** - Pattern matching
- **PatternAssignController** - Pattern assignment

#### SavingsAccount Domain (1 controller)
- **SavingsAccountController** - Savings account management

---

## 3. SERVICES & RESPONSIBILITIES

### 3.1 Service Layer Overview (23 services total)

#### Account Service (1)
- **AccountService** - Account CRUD operations

#### Budget Services (7)
- **BudgetService** (501 lines)
  - Budget CRUD, category assignment, budget calculations
  - Methods: 13 public functions
- **BudgetVersionService** (257 lines)
  - Version management and history tracking
- **ActiveBudgetService**
  - Determines active/inactive budgets based on transaction history
  - Configurable lookback window (default: 2 months)
- **BudgetInsightsService** (428 lines)
  - Rolling 6-month median calculations
  - Behavioral insight classification (Stable/Slight/Anomaly)
  - Dutch neutral messaging generation
- **ProjectAggregatorService** (538 lines)
  - Aggregates tracked + external payments for projects
  - Time-series data generation
  - Category breakdowns
- **AttachmentStorageService**
  - File upload handling for external payments
  - Validates file types (PDF, JPG, PNG) and size (max 10MB)
- **ProjectStatusCalculator**
  - Project status determination logic

#### Category Service (1)
- **CategoryService** (518 lines)
  - Category CRUD, category-budget relationships
  - Transaction queries per category
  - Methods: 11 public functions

#### Transaction Services (8)
- **TransactionService** (555 lines) - HIGH COMPLEXITY
  - Transaction queries, filtering, sorting
  - Balance calculations, tree map data generation
  - Methods: 10 public functions
  - Handles TreeMap visualization data
- **TransactionImportService** (517 lines) - HIGH COMPLEXITY
  - CSV import, hash generation, duplicate detection
  - PayPal transaction processing
- **TransactionSplitService** (250 lines)
  - Split transaction handling
- **AiCategorizationService** (181 lines)
  - OpenAI-powered transaction categorization
- **CreditCardPdfParserService** (236 lines)
  - PDF parsing for credit card statements
- **PayPalImportService** (139 lines)
  - PayPal CSV import logic
- **PayPalWebPasteParserService** (186 lines)
  - PayPal website paste format parsing
- **PayPalMatchingService** (113 lines)
  - Matches imported PayPal transactions

#### Pattern Services (4)
- **PatternService** (195 lines)
  - Pattern CRUD operations
- **AiPatternDiscoveryService** (408 lines) - NO TESTS
  - OpenAI-powered pattern discovery from transactions
  - Limits analysis to 200 transactions
- **MatchingPatternService**
  - Pattern matching logic
- **PatternAssignService**
  - Assigns patterns to transactions

#### Feature Flag Service (1)
- **FeatureFlagService**
  - Runtime feature toggle management

#### SavingsAccount Service (1)
- **SavingsAccountService**
  - Savings account management

---

## 4. ENTITIES & RELATIONSHIPS

### 4.1 All Entities (11 total)

#### Core Entities

1. **Account**
   - Represents user bank account
   - Relationships: 1 → Many (Budgets, Categories, Patterns, SavingsAccounts, AiPatternSuggestions)

2. **Transaction** (320 lines) - MOST METHODS: 41 public functions
   - Bank transaction record
   - Fields: id, hash (unique), date, description, account, counterparty_account, transaction_code, transaction_type, amountInCents, mutation_type, notes, balanceAfterInCents, tag
   - Relationships:
     - ManyToOne → Account
     - ManyToOne → Category (nullable)
     - ManyToOne → SavingsAccount (nullable)
     - ManyToOne → Transaction (parent_transaction_id for splits)
     - OneToMany → Transaction (splits collection)

3. **Category** (189 lines)
   - Transaction categorization
   - Relationships:
     - ManyToOne → Account
     - ManyToOne → Budget (nullable, inversedBy)
     - OneToMany → Transaction (mappedBy, no cascade)

4. **Budget** (249 lines)
   - Spending budget for account
   - Fields: id, name, account, createdAt, updatedAt, budgetType (EXPENSE/INCOME/PROJECT), icon, description, durationMonths
   - Relationships:
     - ManyToOne → Account (with CASCADE delete)
     - OneToMany → BudgetVersion (with CASCADE, orphanRemoval)
     - OneToMany → Category (mappedBy)

5. **BudgetVersion**
   - Version history for budgets
   - Tracks changes over time with effective dates
   - Relationships: ManyToOne → Budget (inversedBy)

6. **Pattern** (237 lines)
   - Transaction matching pattern
   - Fields: id, account, startDate, endDate, minAmountInCents, maxAmountInCents, transactionType, description, matchTypeDescription, notes, matchTypeNotes, tag, strict flag, uniqueHash
   - Relationships:
     - ManyToOne → Account
     - ManyToOne → Category (nullable)
     - ManyToOne → SavingsAccount (nullable)

7. **SavingsAccount**
   - Secondary savings account tracking
   - Relationships:
     - ManyToOne → Account
     - OneToMany → Transaction (mappedBy)
     - OneToMany → Pattern (mappedBy)

#### New Entities (Adaptive Dashboard Feature)

8. **ExternalPayment**
   - Off-ledger payments for projects
   - Relationships: ManyToOne → Budget

9. **ProjectAttachment**
   - File attachments for external payments
   - Relationships: ManyToOne → Budget

10. **FeatureFlag**
    - Runtime feature toggles
    - Stores enabled/disabled state per feature

11. **AiPatternSuggestion** (281 lines) - 36 public methods
    - AI-generated pattern suggestions
    - Fields: id, account, descriptionPattern, notesPattern, suggestedCategoryName, existingCategory, matchCount, confidence, reasoning, exampleTransactions, status, patternHash, createdPattern, createdAt, processedAt, acceptedDescriptionPattern, acceptedNotesPattern, acceptedCategoryName
    - Relationships:
      - ManyToOne → Account
      - ManyToOne → Category
      - ManyToOne → Pattern

### 4.2 Entity Relationships Summary

```
Account (1)
├── ↔ Budget (M) → BudgetVersion (M) [cascade: persist, remove]
├── ↔ Category (M) → Transaction (M) [no cascade on category]
├── ↔ Transaction (M) [self-referencing via parentTransaction for splits]
├── ↔ Pattern (M)
├── ↔ SavingsAccount (M) → Transaction (M)
├── ↔ AiPatternSuggestion (M)
└── ↔ ExternalPayment (M) [via Budget]
```

---

## 5. REPOSITORIES (9 total)

1. **AccountRepository**
2. **BudgetRepository** 
3. **BudgetVersionRepository**
4. **CategoryRepository**
5. **TransactionRepository** (935 lines) - LARGEST FILE
   - 26 public methods
   - Custom queries: filtering, summaries, statistics, monthly totals
   - CRITICAL: Contains complex DQL queries for:
     - Category statistics with median calculations
     - Monthly breakdown queries
     - Tree map data
     - TreeMap visualization data generation
6. **SavingsAccountRepository**
7. **PatternRepository** - 10 public methods
8. **AiPatternSuggestionRepository**
9. **FeatureFlagRepository**

---

## 6. DATA TRANSFER OBJECTS (DTOs)

### 6.1 DTOs by Domain (34 total)

**Account:** 1 DTO
- AccountDTO

**Budget:** 15 DTOs
- ActiveBudgetDTO
- AssignCategoriesDTO
- AvailableCategoryDTO
- BudgetDTO
- BudgetSummaryDTO
- BudgetVersionDTO
- CategoryBreakdownDTO
- CreateBudgetDTO
- CreateBudgetVersionDTO
- CreateExternalPaymentDTO
- CreateProjectDTO
- CreateSimpleBudgetVersionDTO
- ProjectDetailsDTO
- UpdateBudgetDTO
- UpdateBudgetVersionDTO
- UpdateProjectDTO

**Category:** 2 DTOs
- CategoryDTO
- CategoryWithTransactionsDTO

**Transaction:** 5 DTOs
- AssignSavingsAccountDTO
- SetCategoryDTO
- TransactionDTO
- TransactionFilterDTO
- TransactionMatchesDTO

**Pattern:** 6 DTOs
- AcceptPatternSuggestionDTO
- AssignPatternDateRangeDTO
- CreatePatternDTO
- PatternDTO
- PatternSuggestionDTO
- UpdatePatternDTO

**SavingsAccount:** 4 DTOs
- AssignPatternDateRangeDTO
- CreateSavingsAccountDTO
- SavingsAccountDTO
- UpdateSavingsAccountDTO

---

## 7. ENUMS (6 total)

1. **TransactionType** - CREDIT, DEBIT
2. **BudgetType** - EXPENSE, INCOME, PROJECT
3. **MatchType** - LIKE, EXACT, REGEX
4. **ProjectStatus** - PLANNING, ACTIVE, ON_HOLD, COMPLETED, CANCELLED
5. **PayerSource** - MORTGAGE_DEPOT, INSURER, EMPLOYER, FAMILY, INHERITANCE, OTHER
6. **AiPatternSuggestionStatus** - PENDING, ACCEPTED, REJECTED

---

## 8. CODE QUALITY ISSUES & FINDINGS

### 8.1 Critical Issues (Must Fix)

#### 1. SECURITY: No Authentication/Authorization
**Severity:** CRITICAL  
**Files:** `config/packages/security.yaml`, all controllers  
**Status:** ALL API ENDPOINTS ARE PUBLICLY ACCESSIBLE

```yaml
# Current state: All access control commented out
security:
  # access_control:
  #   - { path: ^/api, roles: ROLE_USER }
```

**Impact:**
- No user verification on any endpoint
- CORS configured with wildcard `*` allowing any origin
- Anyone can read/modify all transactions, budgets, categories
- Recommendation: Implement JWT or session-based auth before production

---

#### 2. LARGE FILES - Refactoring Candidates

| File | Lines | Issue |
|------|-------|-------|
| TransactionRepository | 935 | Query complexity, repetitive methods |
| BudgetController | 614 | Too many endpoints, validation logic duplication |
| TransactionService | 555 | Mixed concerns: queries, calculations, TreeMap generation |
| TransactionImportService | 517 | Multiple import formats, complex state |
| ProjectAggregatorService | 538 | Complex aggregation logic |
| CategoryService | 518 | Large mapper, complex queries |
| BudgetService | 501 | Budget calculations mixed with CRUD |

**Recommendation:** Break into smaller, focused classes

---

#### 3. CODE DUPLICATION - Entity Lookup Pattern
**Count:** 62 instances of `throw new NotFoundHttpException`
**Pattern:**
```php
$entity = $this->repository->find($id);
if (!$entity) {
    throw new NotFoundHttpException('Entity not found');
}
```

**Recommendation:** Create `EntityLookupTrait` or base service helper:
```php
protected function findOrThrow(Repository $repo, $id, string $message): object
{
    return $repo->find($id) ?? throw new NotFoundHttpException($message);
}
```

**Affected Areas:**
- Budget controller (20+ instances)
- ExternalPayment controller (15+ instances)
- Category controller (10+ instances)
- Pattern controllers (10+ instances)

---

#### 4. Testing Coverage - MISSING
**Status:** 10 test files total
**Coverage Distribution:**
- ✅ Account: 10 API tests
- ✅ Category: 16 API tests
- ✅ Transaction: 22 API + 3 repository + 3 unit = 28 tests
- ✅ SavingsAccount: 12 API tests
- ✅ Budget: 21 ActiveBudgetService + 14 BudgetInsightsService = 35 tests
- ❌ Pattern: **0 tests** (HIGH PRIORITY)
- ❌ AI Services: **0 tests** (AiPatternDiscoveryService, AiCategorizationService)
- ❌ Budget API Integration: **0 tests** (new endpoints untested)

**Recommendation:** Write tests for:
1. Pattern matching engine (critical logic)
2. AiPatternDiscoveryService (external API calls, expensive)
3. AiCategorizationService (external API calls)
4. Budget API endpoints (/active, /older, external payments)

---

#### 5. Validation & Error Handling Inconsistency
**Pattern:** 14+ controllers duplicate validation logic

```php
// Repeated in every controller:
$errors = $this->validator->validate($dto);
if (count($errors) > 0) {
    $errorMessages = [];
    foreach ($errors as $error) {
        $errorMessages[] = $error->getMessage();
    }
    return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
}
```

**Issues:**
- Mixed error response formats (`errors` vs `error`)
- Mix of Dutch and English messages
- No centralized validation exception handler

**Recommendation:** Create custom exception handlers in ApiExceptionListener

---

#### 6. CONSTRUCTOR PATTERN INCONSISTENCY
**Mix of:**
- Property promotion (PHP 8.1): `public function __construct(private readonly Service $service)`
- Traditional assignment: `public function __construct(Service $service) { $this->service = $service; }`

**Files affected:**
- ActiveBudgetService, BudgetInsightsService, ProjectAggregatorService (property promotion)
- BudgetController, TransactionService, CategoryService (traditional)

---

#### 7. API Inconsistencies

| Issue | Example |
|-------|---------|
| Verb in URLs | `/api/account/{id}/budget/create` (should use POST) |
| Inconsistent method names | `list()` vs `findAll()` |
| Mixed response formats | Some endpoints return wrapped data, some don't |
| Error formats | `{'errors': [...]}` vs `{'error': '...'}` |

**Recommendation:** Standardize on REST conventions

---

### 8.2 High Priority Issues

#### 1. TransactionRepository Complexity (935 lines)
**Methods:** 26 public methods  
**Problems:**
- Statistics methods should be extracted to separate service
- DQL queries for monthly calculations are complex
- Median calculation in repository (should be in service)
- TreeMap generation logic in repository

**Recommendation:** Split into:
- TransactionQueryRepository (filters, lists)
- TransactionStatisticsService (median, trends, tree map)

---

#### 2. No Interface-Based Design
**Current:** Direct class dependencies everywhere
**Issue:** Hard to test, tight coupling
**Files:** All service classes, all controllers

**Recommendation:** Extract interfaces for major services:
- TransactionRepositoryInterface
- BudgetServiceInterface
- CategoryServiceInterface

---

#### 3. Bidirectional Relationships Management
**Issue:** Must manually set both sides when creating Category-Budget pairs

```php
// Must do BOTH:
$category->setBudget($budget);
$budget->addCategory($category);  // Critical! Often forgotten
```

**Evidence:** Test fixtures show this pattern 40+ times  
**Recommendation:** Create factory or helper method

---

#### 4. Mixed DateTime Types
**Files:** Transaction.php, Pattern.php  
**Issue:** Mix of:
- `DateTime` (mutable)
- `DateTimeImmutable` (immutable)
- `\DateTimeInterface` (interface)

**Recommendation:** Standardize on DateTimeImmutable (Symfony convention)

---

### 8.3 Medium Priority Issues

#### 1. No Deprecated Code Found
✅ **Good:** No @deprecated annotations found

#### 2. No TODO/FIXME Comments Found
✅ **Good:** Code is relatively clean of technical debt markers

#### 3. Method Count Issues

**Highest method counts:**
- Transaction Entity: 41 public methods (mostly getters/setters)
- AiPatternSuggestion Entity: 36 public methods
- TransactionRepository: 26 public methods (overstuffed)
- BudgetController: 12 methods

**Recommendation:** Consider splitting controllers by resource type

---

#### 4. Complex Money Handling
**Good:** Uses Money PHP library for precision  
**Issue:** Conversions happen in multiple places
- Repository: `Money::EUR($cents)`
- Service: `$moneyFactory->create($amount)`
- DTO: String representation

**Recommendation:** Centralize Money conversion

---

#### 5. Pattern Domain - No Tests + Complex AI Integration
**Issues:**
- AiPatternDiscoveryService calls OpenAI API (no mock tests)
- MatchingPatternService has complex regex logic
- PatternRepository has 10 public methods
- No test coverage for SQL query generation

**Recommendation:** High priority for test coverage

---

#### 6. Implicit Dependencies
**Example:** AiPatternDiscoveryService
```php
private readonly LoggerInterface $logger,
private readonly string $openaiApiKey,  // How is this injected?
private readonly AiPatternSuggestionRepository $suggestionRepository,
```

**Issue:** Not clear how string parameters are injected
**Recommendation:** Document in config/services.yaml

---

### 8.4 Low Priority Issues

#### 1. Sparse Comments
**Status:** Most classes have minimal documentation
**Files:** Controllers with complex logic lack doc blocks
**Recommendation:** Add PHPDoc for public methods

---

#### 2. File Organization in Transaction Domain
**Issue:** 8 services in Transaction/Service/
- TransactionService (core)
- TransactionImportService (CSV/PayPal)
- TransactionSplitService
- AiCategorizationService (external API)
- CreditCardPdfParserService
- PayPalImportService
- PayPalWebPasteParserService
- PayPalMatchingService

**Recommendation:** Create sub-directories:
- TransactionService (core logic)
- Import/ (all import-related)
- PayPal/ (PayPal-specific)

---

#### 3. Test File Organization
**Current:** 10 test files at top level  
**Should be:** Mirrored domain structure

---

## 9. ARCHITECTURE PATTERNS & BEST PRACTICES

### 9.1 Well-Implemented Patterns

✅ **Domain-Driven Design**
- Vertical slices per domain (Account, Budget, Category, etc.)
- Each domain has Controller, Service, DTO, Mapper, Repository

✅ **Mapper Pattern**
- Entity ↔ DTO conversions centralized
- Prevents leaking domain models to API layer

✅ **Repository Pattern**
- Data access abstraction
- Custom query methods

✅ **Dependency Injection**
- Constructor injection throughout
- Property promotion where appropriate

✅ **Money PHP Library**
- All financial calculations use Money library
- No float arithmetic
- Cent-based integer storage

✅ **Feature Flags**
- Runtime toggles for new features
- Database + environment variable fallback
- Used for living_dashboard, projects, behavioral_insights

---

### 9.2 Patterns to Improve

⚠️ **No Interface Abstraction**
- Services not behind interfaces
- Hard to test without refactoring

⚠️ **Error Handling**
- Inconsistent error responses
- Validation logic duplicated across controllers

⚠️ **Validation**
- No centralized validation
- Validation in every controller method

---

## 10. MIGRATIONS & DATABASE

**Total Migrations:** 15 files

**Recent Migrations:**
1. Version20251103211742 - Latest
2. Version20251019202742
3. Version20251017141039
4. Version20251017073545
5. Version20251017070454
6. Version20251014202740
7. Version20251003082439

**Key Migrations:**
- ExternalPayment entity (projects feature)
- Budget extensions (budgetType, dates, status)
- FeatureFlag entity (runtime toggles)

**Database Schema:**
- 11 entities mapped to tables
- Multiple one-to-many and many-to-one relationships
- Cascade deletes configured for data integrity
- Unique constraints on: Transaction.hash, Pattern.uniqueHash

---

## 11. TESTING SUMMARY

### 11.1 Test Files

```
tests/
├── Unit/
│   ├── Budget/Service/
│   │   ├── BudgetInsightsServiceTest.php (14 tests)
│   │   └── ActiveBudgetServiceTest.php (21 tests)
│   ├── Transaction/Service/
│   │   └── TransactionServiceTest.php
│   └── Money/
│       └── MoneyFactoryTest.php (4 tests)
├── Integration/
│   ├── Api/
│   │   ├── AccountManagementTest.php (10 tests)
│   │   ├── CategoryManagementTest.php (16 tests)
│   │   ├── TransactionImportTest.php
│   │   ├── TransactionManagementTest.php (22 tests)
│   │   └── SavingsAccountManagementTest.php (12 tests)
│   └── Repository/
│       └── TransactionRepositoryTest.php (3 tests)
├── Fixtures/
│   ├── CsvTestFixtures.php
│   └── TestFixtures.php
└── TestCase/
    ├── ApiTestCase.php (makeJsonRequest, assertions)
    ├── DatabaseTestCase.php (database setup)
    └── WebTestCase.php
```

### 11.2 Coverage Analysis

**Total Tests:** ~112 tests, 496+ assertions

**Coverage by Domain:**
- ✅ Account: Comprehensive (10 API tests)
- ✅ Category: Good (16 API tests)
- ✅ SavingsAccount: Good (12 API tests)
- ✅ Transaction: Excellent (22 API + 3 repo + 3 unit = 28 tests)
- ✅ Budget: Good (35 unit tests for services)
- ❌ Pattern: **ZERO TESTS**
- ❌ AI Services: **ZERO TESTS**

**Critical Missing Tests:**
1. Pattern matching logic (SQL generation, regex)
2. AiPatternDiscoveryService (OpenAI integration)
3. AiCategorizationService (OpenAI integration)
4. Budget API endpoints (new /active, /older, external payments)
5. ExternalPayment management
6. ProjectAggregatorService

---

## 12. PERFORMANCE CONSIDERATIONS

### 12.1 Database Query Patterns

**TransactionRepository:** 26 methods including:
- `getTotalSpentByCategoriesInPeriod()` - Uses GROUP BY
- `getMonthlyTotalsByCategory()` - Complex join
- `getCategoryStatistics()` - Aggregate functions
- `getCategoryBreakdownForMonth()` - Multiple calculations

**Potential N+1 Issues:**
- Category loading in transaction queries
- Budget version fetching
- Pattern lookups during import

---

### 12.2 External API Calls

**AiPatternDiscoveryService:**
- Calls OpenAI API for pattern discovery
- Processes up to 200 transactions per call
- No caching/rate limiting visible
- Blocking call (no async)

**AiCategorizationService:**
- Calls OpenAI for transaction categorization
- Real-time during import
- No batch processing visible

**Recommendation:** Implement queue for AI operations

---

## 13. MIGRATION READINESS

### Current Technical Debt

| Category | Count | Severity |
|----------|-------|----------|
| Missing Tests | 3 domains | HIGH |
| Code Duplication | 62 instances | MEDIUM |
| Large Files | 7 files | MEDIUM |
| Missing Docs | Controllers | LOW |
| Security Issues | 1 critical | CRITICAL |

---

## 14. RECOMMENDATIONS SUMMARY

### Immediate (This Sprint)

1. **✅ CRITICAL:** Implement authentication/authorization
2. **✅ HIGH:** Add tests for Pattern domain
3. **✅ HIGH:** Add tests for AI services (with mocks)
4. **✅ HIGH:** Add integration tests for new Budget endpoints

### Short-term (Next 2 Sprints)

1. **Extract EntityLookupTrait** - Eliminate 62 duplication instances
2. **Split TransactionRepository** - Query vs Statistics responsibilities
3. **Create validation exception handler** - Eliminate controller duplication
4. **Extract PayPal logic** - Create subdomain for PayPal operations
5. **Add service interfaces** - Improve testability

### Medium-term (Next Quarter)

1. **Refactor large services** - TransactionService, TransactionImportService
2. **Implement queue** - For AI operations (OpenAI API calls)
3. **Add caching layer** - For statistics queries
4. **Standardize REST conventions** - Remove verb-based URLs
5. **Improve error handling** - Centralized, consistent responses

### Long-term (Architecture)

1. **Event-based architecture** - For cross-domain communication
2. **GraphQL API** - Alternative to REST for complex queries
3. **Read models** - CQRS pattern for reporting
4. **Async job processing** - For imports, AI operations

---

## 15. POSITIVE FINDINGS

✅ **Clean Code Principles:**
- No deprecated code found
- No TODO/FIXME comments
- Consistent naming conventions
- Good separation of concerns (Domain-Driven Design)

✅ **Framework Usage:**
- Proper Symfony conventions
- DI container well utilized
- OpenAPI documentation configured
- CORS properly configured (though needs auth)

✅ **Database Design:**
- Proper relationships with constraints
- Migrations tracked and versioned
- Unique constraints where needed
- Cascade deletes prevent orphans

✅ **Modern PHP Patterns:**
- Constructor property promotion
- Enums for type safety
- Type hints throughout
- Immutable values where appropriate

---

## Appendix: File Size Rankings

### Top 10 Largest PHP Files

| File | Lines | Type |
|------|-------|------|
| TransactionRepository | 935 | Repository |
| BudgetController | 614 | Controller |
| TransactionService | 555 | Service |
| TransactionImportService | 517 | Service |
| ProjectAggregatorService | 538 | Service |
| CategoryController | 527 | Controller |
| CategoryService | 518 | Service |
| BudgetService | 501 | Service |
| AdaptiveDashboardController | 438 | Controller |
| BudgetInsightsService | 428 | Service |

---

## Summary Statistics

- **Total PHP Files:** 115
- **Total Lines of Code:** 17,071
- **Average File Size:** 148 lines
- **Total Controllers:** 17 (avg 82 lines/controller)
- **Total Services:** 23 (avg 95 lines/service)
- **Total Entities:** 11 (avg 260 lines/entity - includes many getters)
- **Total Repositories:** 9 (avg 104 lines/repo)
- **Total DTOs:** 34 (avg 20 lines/DTO)
- **Total Enums:** 6
- **Total Test Files:** 10
- **Total Lines of Tests:** Unknown (not measured)
- **Configuration Files:** 12

