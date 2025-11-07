# Code Quality Report - Mister Munney

**Date:** November 6, 2025
**Focus:** Code duplication, complexity, refactoring opportunities

---

## 📊 Overall Code Quality Metrics

### Backend (PHP/Symfony)
- **Total Lines:** 17,071 LOC
- **Files:** 115+ PHP files
- **Average File Size:** 148 LOC
- **Largest File:** TransactionRepository.php (935 lines)
- **Controllers:** 17 files, 4,914 total lines
- **Services:** 22 files, 6,000 total lines
- **Repositories:** 9 files, 1,619 total lines

### Frontend (React/TypeScript)
- **Total Lines:** 17,298 LOC
- **Files:** 132 TS/TSX files
- **Average File Size:** 131 LOC
- **Largest File:** ProjectDetailPage.tsx (751 lines)

---

## 🔴 CRITICAL CODE QUALITY ISSUES

### 1. Entity Lookup Pattern Duplication
**Priority:** 🟡 HIGH | **Effort:** M | **Impact:** HIGH

**Current Situation:**
Found **73 instances** across 18 files of identical entity lookup code:

```php
$entity = $this->repository->find($id);
if (!$entity) {
    throw new NotFoundHttpException('Entity not found');
}
```

**Files with highest occurrences:**
- `backend/src/Budget/Service/BudgetService.php`: 11 instances
- `backend/src/Transaction/Service/TransactionService.php`: 9 instances
- `backend/src/Pattern/Service/PatternService.php`: 8 instances
- `backend/src/Command/MigrateCategoriesFromOldDbCommand.php`: 6 instances
- `backend/src/Budget/Controller/ExternalPaymentController.php`: 6 instances
- `backend/src/Budget/Controller/ProjectAttachmentController.php`: 5 instances
- `backend/src/Budget/Controller/AdaptiveDashboardController.php`: 5 instances
- `backend/src/Transaction/Service/TransactionSplitService.php`: 5 instances
- `backend/src/Category/Service/CategoryService.php`: 4 instances

**Why This Matters:**
- Violates DRY (Don't Repeat Yourself) principle
- ~250 lines of duplicated code
- Inconsistent error messages across files
- Hard to modify behavior (73 places to change)
- Increases cognitive load when reading code

**Recommended Solution:**

```php
<?php
// backend/src/Shared/Trait/EntityLookupTrait.php

namespace App\Shared\Trait;

use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait EntityLookupTrait
{
    /**
     * Find entity by ID or throw 404 exception
     *
     * @template T
     * @param ObjectRepository<T> $repository
     * @param int $id
     * @param string $entityName
     * @return T
     * @throws NotFoundHttpException
     */
    private function findOrFail(ObjectRepository $repository, int $id, string $entityName): object
    {
        $entity = $repository->find($id);

        if (!$entity) {
            throw new NotFoundHttpException(
                sprintf('%s not found with ID: %d', $entityName, $id)
            );
        }

        return $entity;
    }
}
```

**Usage:**
```php
class BudgetService
{
    use EntityLookupTrait;

    public function getBudget(int $id): Budget
    {
        // Before: 3 lines
        // $budget = $this->budgetRepository->find($id);
        // if (!$budget) {
        //     throw new NotFoundHttpException('Budget not found');
        // }

        // After: 1 line
        return $this->findOrFail($this->budgetRepository, $id, 'Budget');
    }
}
```

**Implementation Steps:**
1. Create `EntityLookupTrait.php` in `backend/src/Shared/Trait/`
2. Update services one by one (start with BudgetService)
3. Test each service after refactoring
4. Remove old code

**Expected Impact:**
- Reduces ~220 lines of duplicated code
- Consistent error messages
- Easier to add logging/metrics in one place
- Improves maintainability

---

### 2. Validation Error Handling Duplication
**Priority:** 🟡 HIGH | **Effort:** M | **Impact:** MEDIUM

**Current Situation:**
Found **14+ controllers** with identical validation logic:

**Files with duplication:**
- BudgetController.php
- BudgetVersionController.php
- CategoryController.php
- TransactionController.php
- PatternController.php
- SavingsAccountController.php
- ExternalPaymentController.php
- ProjectAttachmentController.php
- AdaptiveDashboardController.php
- AiPatternDiscoveryController.php
- And 4+ more...

**Duplicated Code:**
```php
// This appears in 14+ controllers
$errors = $this->validator->validate($dto);
if (count($errors) > 0) {
    $errorMessages = [];
    foreach ($errors as $error) {
        $errorMessages[] = $error->getMessage();
    }
    return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
}
```

**Why This Matters:**
- ~140 lines of duplicated code (10 lines × 14 files)
- Inconsistent error response format
- Hard to enhance (add field names, error codes)
- Violates DRY principle

**Recommended Solution:**

```php
<?php
// backend/src/Shared/Trait/ValidationTrait.php

namespace App\Shared\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

trait ValidationTrait
{
    private ValidatorInterface $validator;

    /**
     * Validate DTO and return error response if invalid
     *
     * @return JsonResponse|null Returns JsonResponse with errors or null if valid
     */
    protected function validateDto(object $dto): ?JsonResponse
    {
        $errors = $this->validator->validate($dto);

        if (count($errors) === 0) {
            return null;
        }

        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[$error->getPropertyPath()] = $error->getMessage();
        }

        return $this->json([
            'errors' => $errorMessages,
            'message' => 'Validation failed'
        ], Response::HTTP_BAD_REQUEST);
    }
}
```

**Usage:**
```php
class BudgetController extends AbstractController
{
    use ValidationTrait;

    public function createBudget(Request $request): JsonResponse
    {
        $dto = $this->serializer->deserialize(
            $request->getContent(),
            CreateBudgetDTO::class,
            'json'
        );

        // Before: 8 lines
        // $errors = $this->validator->validate($dto);
        // if (count($errors) > 0) {
        //     $errorMessages = [];
        //     foreach ($errors as $error) {
        //         $errorMessages[] = $error->getMessage();
        //     }
        //     return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        // }

        // After: 2 lines
        if ($errorResponse = $this->validateDto($dto)) {
            return $errorResponse;
        }

        // Continue with business logic...
    }
}
```

**Benefits:**
- Removes ~140 lines of duplicated code
- Consistent error format (with field names!)
- Easy to enhance (add i18n, error codes, etc.)
- Improves error messages for frontend

---

## 🟡 HIGH PRIORITY CODE QUALITY ISSUES

### 3. Large Files Exceeding Complexity Limits
**Priority:** 🟡 HIGH | **Effort:** L | **Impact:** MEDIUM

**Backend Files:**

#### `TransactionRepository.php` - 935 lines ❌
**Location:** `backend/src/Transaction/Repository/TransactionRepository.php`

**Issues:**
- Multiple responsibilities (filtering, statistics, aggregation)
- 20+ methods in single class
- Complex query building logic
- Hard to test individual pieces

**Methods by category:**
- **Filtering:** `findByFilter()`, `applyFilter()` (15 methods)
- **Statistics:** `summaryByFilter()`, `getMonthlyTrend()`, `getCategoryBreakdown()` (8 methods)
- **Aggregation:** `getTotalByCategory()`, `getSpendingByMonth()` (5 methods)

**Recommended Split:**
```
TransactionRepository.php (400 lines)
  - Core CRUD operations
  - Basic filtering

TransactionStatisticsRepository.php (300 lines)
  - summaryByFilter()
  - getMonthlyTrend()
  - getCategoryBreakdown()
  - getSpendingByMonth()

TransactionQueryBuilder.php (235 lines)
  - applyFilter() logic
  - Complex query construction
  - Reusable query building
```

**Benefits:**
- Single Responsibility Principle
- Easier to test
- Easier to understand
- Faster to navigate

---

#### `BudgetController.php` - 614 lines ❌
**Location:** `backend/src/Budget/Controller/BudgetController.php`

**Issues:**
- Too many endpoints in one controller
- Mixing budget CRUD, version management, and category assignment
- 18+ public methods
- Hard to maintain

**Current endpoints:**
- Budget CRUD: `create()`, `get()`, `update()`, `delete()`, `list()`
- Version management: `createVersion()`, `updateVersion()`, `deleteVersion()`
- Category assignment: `assignCategories()`, `removeCategory()`
- Summaries: `getBudgetSummaries()`, `getCategoryBreakdown()`

**Recommended Split:**
```
BudgetController.php (200 lines)
  - create() - POST /api/account/{id}/budget
  - get() - GET /api/account/{id}/budget/{budgetId}
  - update() - PUT /api/account/{id}/budget/{budgetId}
  - delete() - DELETE /api/account/{id}/budget/{budgetId}
  - list() - GET /api/account/{id}/budget

BudgetCategoryController.php (150 lines)
  - assignCategories() - PUT /api/account/{id}/budget/{budgetId}/categories
  - removeCategory() - DELETE /api/account/{id}/budget/{budgetId}/categories/{categoryId}

BudgetSummaryController.php (150 lines)
  - getBudgetSummaries() - GET /api/account/{id}/budget/summary/{month}
  - getCategoryBreakdown() - GET /api/account/{id}/budget/{budgetId}/breakdown/{month}

NOTE: BudgetVersionController already exists separately (good!)
```

---

#### `TransactionService.php` - 555 lines ❌
**Location:** `backend/src/Transaction/Service/TransactionService.php`

**Issues:**
- Business logic mixed with statistics
- 15+ methods
- Multiple concerns

**Recommended Split:**
```
TransactionService.php (300 lines)
  - createTransaction()
  - updateTransaction()
  - deleteTransaction()
  - setCategory()
  - setSavingsAccount()

TransactionStatisticsService.php (255 lines)
  - calculateStatistics()
  - getMonthlyTrend()
  - getCategoryBreakdown()
```

---

#### `ProjectAggregatorService.php` - 538 lines ⚠️
**Location:** `backend/src/Budget/Service/ProjectAggregatorService.php`

**Issues:**
- Complex aggregation logic
- Time series calculations
- Entry merging logic

**Recommended Split:**
```
ProjectAggregatorService.php (300 lines)
  - getProjectTotals()
  - getProjectEntries()

ProjectTimeSeriesService.php (238 lines)
  - getProjectTimeSeries()
  - calculateMonthlyBars()
  - calculateCumulativeLine()
```

---

#### `CategoryController.php` - 527 lines ❌
**Location:** `backend/src/Category/Controller/CategoryController.php`

**Recommended Split:**
```
CategoryController.php (200 lines)
  - CRUD operations

CategoryMergeController.php (150 lines)
  - mergePreview()
  - merge()

CategoryStatisticsController.php (177 lines)
  - getStatistics()
  - getByCategory()
```

---

**Frontend Files:**

#### `ProjectDetailPage.tsx` - 751 lines ❌
**Location:** `frontend/src/domains/budgets/ProjectDetailPage.tsx`

**Issues:**
- Too much logic in one component
- Multiple tabs with different data
- Complex state management
- Hard to test

**Recommended Split:**
```tsx
// ProjectDetailPage.tsx (200 lines)
- Main layout and tab navigation
- State management
- Data loading

// components/ProjectOverviewTab.tsx (200 lines)
- Overview statistics
- Charts
- Project info

// components/ProjectEntriesTab.tsx (200 lines)
- Transaction and payment entries
- Table rendering
- Filtering

// components/ProjectFilesTab.tsx (151 lines)
- File attachments
- Upload handling
- File list
```

---

#### `PatternDiscovery.tsx` - 539 lines ❌
**Location:** `frontend/src/domains/patterns/components/PatternDiscovery.tsx`

**Recommended Split:**
```tsx
// PatternDiscovery.tsx (200 lines)
- Main layout
- State management

// hooks/useAiPatternSuggestions.ts (150 lines)
- AI suggestion logic
- API calls
- State management

// components/PatternSuggestionList.tsx (189 lines)
- Suggestion rendering
- Accept/reject actions
```

---

#### `TransactionFilterForm.tsx` - 404 lines ⚠️
**Location:** `frontend/src/domains/transactions/components/TransactionFilterForm.tsx`

**Recommended Split:**
```tsx
// TransactionFilterForm.tsx (150 lines)
- Main form layout
- Form submission

// components/DateRangeFilter.tsx (80 lines)
- Date range picker

// components/CategoryFilter.tsx (80 lines)
- Category selection

// components/AmountFilter.tsx (94 lines)
- Amount range filter
```

---

### 4. No Service Interfaces
**Priority:** 🟡 MEDIUM | **Effort:** M | **Impact:** MEDIUM

**Current Situation:**
All services are concrete classes without interfaces:
- `BudgetService.php`
- `TransactionService.php`
- `CategoryService.php`
- `PatternService.php`
- And 18 more services...

**Why This Matters:**
- Hard to mock in tests
- Tight coupling between layers
- Violates Dependency Inversion Principle (SOLID)
- Can't easily swap implementations

**Example Problem:**
```php
class BudgetController extends AbstractController
{
    // Tightly coupled to concrete class
    public function __construct(private BudgetService $budgetService) {}

    // Can't easily mock $budgetService in tests
    // Can't swap implementation without changing controller
}
```

**Recommended Solution:**

```php
<?php
// backend/src/Budget/Service/BudgetServiceInterface.php

namespace App\Budget\Service;

use App\Entity\Budget;
use App\Budget\DTO\CreateBudgetDTO;
use App\Budget\DTO\UpdateBudgetDTO;

interface BudgetServiceInterface
{
    public function createBudget(CreateBudgetDTO $dto): Budget;
    public function getBudget(int $id): Budget;
    public function updateBudget(int $id, UpdateBudgetDTO $dto): Budget;
    public function deleteBudget(int $id): void;
    public function listBudgets(int $accountId): array;
}
```

```php
// backend/src/Budget/Service/BudgetService.php

class BudgetService implements BudgetServiceInterface
{
    // ... implementation
}
```

```yaml
# config/services.yaml
services:
    # Bind interface to implementation
    App\Budget\Service\BudgetServiceInterface:
        alias: App\Budget\Service\BudgetService
```

```php
// Controllers now depend on interface
class BudgetController extends AbstractController
{
    public function __construct(
        private BudgetServiceInterface $budgetService
    ) {}
}
```

**Benefits:**
- Easy to mock in tests
- Loose coupling
- Can swap implementations
- Follows SOLID principles

**Services that need interfaces (priority order):**
1. ✅ BudgetService
2. ✅ TransactionService
3. ✅ CategoryService
4. ✅ PatternService
5. ⚠️ AccountService
6. ⚠️ SavingsAccountService
7. ⚠️ AiCategorizationService
8. ⚠️ TransactionImportService

---

### 5. Constructor Parameter Inconsistency
**Priority:** 🟢 LOW | **Effort:** S | **Impact:** LOW

**Current Situation:**
Mix of PHP 8.0 constructor property promotion and traditional property assignment:

**Modern (Property Promotion) - 60% of files:**
```php
public function __construct(
    private BudgetService $budgetService,
    private BudgetMapper $budgetMapper,
) {}
```

**Old Style - 40% of files:**
```php
private BudgetService $budgetService;
private BudgetMapper $budgetMapper;

public function __construct(
    BudgetService $budgetService,
    BudgetMapper $budgetMapper
) {
    $this->budgetService = $budgetService;
    $this->budgetMapper = $budgetMapper;
}
```

**Why This Matters:**
- Inconsistency makes code harder to read
- Old style is more verbose (3x lines of code)
- PHP 8.0+ has better syntax available

**Recommended:** Use property promotion everywhere (modern syntax)

**Files to update:**
- `backend/src/Transaction/Repository/TransactionRepository.php`
- `backend/src/Category/Repository/CategoryRepository.php`
- `backend/src/EventListener/ApiExceptionListener.php`
- And ~10 other files

---

## 🟢 MEDIUM PRIORITY CODE QUALITY ISSUES

### 6. Lack of Inline Documentation
**Priority:** 🟢 MEDIUM | **Effort:** M | **Impact:** LOW

**Current Situation:**
- Most methods have type hints ✅
- Many methods missing PHPDoc blocks ⚠️
- Complex algorithms lack explanation ⚠️

**Examples needing docs:**

```php
// backend/src/Budget/Service/BudgetInsightsService.php

// Missing explanation of algorithm
private function computeNormal(array $monthlyTotals): Money
{
    // What is "normal"? Median? Average?
    // Why 6 months?
    // What if there are fewer than 6 months?
}

// Missing parameter documentation
public function computeBudgetInsight(
    Budget $budget,
    string $currentMonth,
    int $lookbackMonths = 6
): ?BehavioralInsight {
    // What format is $currentMonth? YYYY-MM?
    // What does lookbackMonths control?
}
```

**Recommended:**
```php
/**
 * Compute "normal" baseline spending using rolling 6-month median.
 *
 * The median is used instead of average to avoid outliers skewing results.
 * Only considers the last 6 COMPLETE months (excludes current month).
 *
 * @param array<string, Money> $monthlyTotals Keyed by YYYY-MM format
 * @return Money The median monthly spending amount
 * @throws \LogicException If fewer than 2 months of data available
 */
private function computeNormal(array $monthlyTotals): Money
{
    // Implementation...
}
```

**Files needing better documentation:**
1. `BudgetInsightsService.php` - Complex insight algorithm
2. `ProjectAggregatorService.php` - Aggregation logic
3. `TransactionRepository.php` - Complex queries
4. `PatternService.php` - Pattern matching logic
5. `AiPatternDiscoveryService.php` - AI integration

---

### 7. Magic Numbers and Strings
**Priority:** 🟢 MEDIUM | **Effort:** S | **Impact:** LOW

**Current Situation:**

```php
// backend/src/Budget/Service/BudgetInsightsService.php
if ($deltaPercent < 0.10) {
    $level = 'stable';
} elseif ($deltaPercent < 0.30) {
    $level = 'slight';
} else {
    $level = 'anomaly';
}
```

**Why This Matters:**
- Hard to understand intent
- Magic numbers scattered across code
- Hard to change thresholds

**Recommended:**
```php
class BudgetInsightsService
{
    // Define as class constants
    private const THRESHOLD_STABLE = 0.10;    // 10%
    private const THRESHOLD_SLIGHT = 0.30;    // 30%

    private const LEVEL_STABLE = 'stable';
    private const LEVEL_SLIGHT = 'slight';
    private const LEVEL_ANOMALY = 'anomaly';

    private function classifyInsightLevel(float $deltaPercent): string
    {
        return match(true) {
            $deltaPercent < self::THRESHOLD_STABLE => self::LEVEL_STABLE,
            $deltaPercent < self::THRESHOLD_SLIGHT => self::LEVEL_SLIGHT,
            default => self::LEVEL_ANOMALY,
        };
    }
}
```

**Other magic numbers to extract:**
- `6` - lookback months for insights
- `2` - minimum months for active budget
- `10485760` - max file upload size (10MB)
- Various HTTP status codes

---

### 8. Error Message Inconsistency
**Priority:** 🟢 LOW | **Effort:** S | **Impact:** LOW

**Current Situation:**
Mix of Dutch and English error messages:

```php
// Dutch
throw new NotFoundHttpException('Budget niet gevonden');

// English
throw new NotFoundHttpException('Budget not found');

// Mix
throw new NotFoundHttpException('Category not found for this account');
```

**Why This Matters:**
- Inconsistent user experience
- Hard to maintain translations
- API should have consistent language

**Recommended Strategy:**

**Option 1: All English (API standard)**
```php
throw new NotFoundHttpException('Budget not found');
```

**Option 2: Translation Keys**
```php
throw new TranslatableException('errors.budget.not_found', ['id' => $id]);
```

**Recommendation:** Use English for API errors (international standard)
Use Symfony translation system if multi-language support is needed

---

## 🔵 LOW PRIORITY CODE QUALITY ISSUES

### 9. Unused Imports
**Priority:** 🔵 LOW | **Effort:** XS | **Impact:** LOW

**Current Situation:**
Some files have unused imports:

```php
use Symfony\Component\HttpFoundation\File\Exception\FileException;
// Never used in file
```

**Recommended:** Run PHPStan/Psalm to detect unused imports

**Quick Fix:**
```bash
docker exec money-backend vendor/bin/phpstan analyze src --level=1
```

---

### 10. Commented Out Code
**Priority:** 🔵 LOW | **Effort:** XS | **Impact:** LOW

**Found commented code blocks in:**
- `backend/config/packages/security.yaml` (access control)
- Some service files (old implementations)

**Recommended:** Remove commented code (use git history if needed)

---

## 📈 Code Quality Improvement Metrics

### Current State:
- **DRY Violations:** 73 entity lookups, 14 validation handlers
- **Large Files:** 7 files >500 lines
- **Interfaces:** 0% of services have interfaces
- **Documentation:** ~40% of complex methods have PHPDoc
- **Consistency:** ~60% use modern PHP 8 syntax

### Target State (After Refactoring):
- **DRY Violations:** 0 (using traits)
- **Large Files:** 0 files >500 lines (all split appropriately)
- **Interfaces:** 100% of major services have interfaces
- **Documentation:** 90%+ of complex methods have PHPDoc
- **Consistency:** 100% use modern PHP 8 syntax

### Estimated Effort:
- **Entity Lookup Trait:** 8 hours
- **Validation Trait:** 6 hours
- **File Splitting:** 24 hours (7 files × ~3.5 hours each)
- **Service Interfaces:** 16 hours (8 services × 2 hours each)
- **Documentation:** 12 hours
- **Consistency Fixes:** 4 hours

**Total:** ~70 hours (8.75 developer days)

---

## 🎯 Recommended Refactoring Order

### Phase 1: Quick Wins (2 days)
1. Create `EntityLookupTrait`
2. Create `ValidationTrait`
3. Remove commented code
4. Fix import issues

### Phase 2: File Splitting (5 days)
1. Split `TransactionRepository` → 3 files
2. Split `BudgetController` → 3 files
3. Split `TransactionService` → 2 files
4. Split `ProjectAggregatorService` → 2 files
5. Split `CategoryController` → 3 files
6. Split `ProjectDetailPage.tsx` → 4 files
7. Split `PatternDiscovery.tsx` → 3 files

### Phase 3: Architecture Improvements (2 days)
1. Add service interfaces for 8 major services
2. Update dependency injection
3. Update tests

### Phase 4: Documentation & Polish (2 days)
1. Add PHPDoc to complex methods
2. Extract magic numbers
3. Standardize error messages

---

**Document Location:** `./claude_improvements/02_code_quality_report.md`
**Last Updated:** November 6, 2025
**Status:** ✅ Ready for Review
