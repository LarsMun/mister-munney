# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Munney is a personal finance management application built with Symfony 7.2 (PHP 8.3) and React 19 (TypeScript). It allows users to:
- Import bank transactions via CSV
- Auto-categorize transactions using pattern matching
- Track budgets with behavioral insights
- Manage projects with external payments
- Visualize spending through interactive charts
- Leverage AI-powered categorization (optional)

### Key Features

**Adaptive Dashboard** (Feature Flag: `living_dashboard`)
- Dynamic budget classification (Active vs Older based on transaction history)
- Behavioral insights with rolling median baselines
- Neutral coaching messages based on spending patterns
- Mini-charts (sparklines) showing 6-month trends

**Projects** (Feature Flag: `projects`)
- PROJECT-type budgets for one-time initiatives (renovations, events)
- External payment tracking (off-ledger payments from mortgage depot, insurer, etc.)
- Comprehensive project aggregation (tracked transactions + external payments)
- Time-series visualization (monthly bars + cumulative line)
- File attachment support for payment receipts

**Behavioral Insights** (Feature Flag: `behavioral_insights`)
- Rolling 6-month median as "normal" baseline
- Three-tier classification: Stable (<10%), Slight (10-30%), Anomaly (>30%)
- Dutch neutral coaching messages
- Visual indicators without judgmental language

## Development Setup

### Environment Configurations

This project has **3 separate Docker environments**:

1. **Local (WSL2/Windows)**: Uses `docker-compose.yml` + `Dockerfile`
   - Clean setup without volume mounts for frontend (avoids WSL2 issues)
   - Frontend code is baked into the image (rebuild container for code changes)

2. **Dev Server** (devmunney.home.munne.me): Uses `deploy/ubuntu/docker-compose.dev.yml` + `Dockerfile`
   - Volume mounts enabled for hot reload
   - Traefik integration for HTTPS

3. **Prod Server** (munney.home.munne.me): Uses `docker-compose.prod.yml` + `Dockerfile.prod`
   - Production builds with Nginx
   - Read-only volume mounts

### Starting the Application (Local)

```bash
# Start all containers
docker compose up -d

# Wait for containers to be healthy
docker compose ps

# Run database migrations (first time or after pulling new migrations)
docker exec money-backend php bin/console doctrine:migrations:migrate

# Access points:
# - Frontend: http://localhost:3000 (Vite dev server)
# - Backend API: http://localhost:8787
# - Database: localhost:3333
```

**Important for Local Development:**
- Frontend changes require rebuilding the container: `docker compose build frontend && docker compose up -d frontend`
- Backend has volume mount so changes are immediate (just refresh browser)
- Database migrations persist in the db_data volume

### Backend Commands

All backend commands run inside the `money-backend` container:

```bash
# Database migrations
docker exec money-backend php bin/console doctrine:migrations:migrate
docker exec money-backend php bin/console doctrine:migrations:generate

# Run tests
docker exec money-backend php bin/phpunit

# Run specific test
docker exec money-backend php bin/phpunit tests/Unit/CategoryServiceTest.php

# Cache management
docker exec money-backend php bin/console cache:clear

# List all console commands
docker exec money-backend php bin/console list
```

### Frontend Commands

Frontend commands run inside the `money-frontend` container:

```bash
# Run any npm command
docker exec money-frontend npm run [command]

# Common commands:
docker exec money-frontend npm run build
docker exec money-frontend npm run lint

# View frontend logs
docker logs money-frontend -f
```

### Database Access

```bash
# Via Docker CLI
docker exec -it money-mysql mysql -u money -p money_db
# Password: moneymakestheworldgoround

# Via external client:
# Host: localhost
# Port: 3333 (dev) or 3334 (prod)
# Database: money_db (dev) or money_db_prod (prod)
```

## Architecture

### Backend Architecture (Symfony)

The backend follows a **Domain-Driven Design** structure with vertical slices. Each domain is self-contained in its own directory:

```
backend/src/
├── Account/
├── Budget/          # ⭐ Enhanced with Projects & Insights
├── Category/
├── FeatureFlag/     # 🆕 Runtime feature toggles
├── Pattern/
├── SavingsAccount/
└── Transaction/
```

Each domain contains:
- **Controller/** - REST API endpoints (prefixed with `/api`)
- **Service/** - Business logic layer
- **Repository/** - Doctrine repositories for database queries
- **DTO/** - Data Transfer Objects for API requests/responses
- **Mapper/** - Converts between Entities and DTOs
- **EventListener/** - Domain-specific event listeners (if needed)

**Shared directories:**
- **Entity/** - Doctrine entities (database models) shared across domains
- **Enum/** - Application-wide enums (e.g., `TransactionType`, `BudgetType`, `ProjectStatus`, `PayerSource`)
- **Money/** - Contains `MoneyFactory` for Money PHP library operations
- **Mapper/** - Contains `PayloadMapper` for common mapping operations

#### New Budget Domain Services

**ActiveBudgetService** - Determines which budgets are "active" vs "older"
- For EXPENSE/INCOME budgets: Active if has ≥1 transaction in last N months (default: 2)
- For PROJECT budgets: Active if status=ACTIVE OR current date within date range
- Configurable via `MUNNEY_ACTIVE_BUDGET_MONTHS` env var

**BudgetInsightsService** - Computes behavioral insights
- `computeNormal()`: Rolling median over last 6 complete months
- `computeBudgetInsight()`: Compares current month to normal baseline
- `getSparklineData()`: Returns monthly totals as float array for mini-charts
- Thresholds: <10% = stable, 10-30% = slight, ≥30% = anomaly
- Dutch neutral messaging: "Stabiel", "Iets hoger/lager", "Opvallend hoger/lager"

**ProjectAggregatorService** - Aggregates project data
- `getProjectTotals()`: Sums tracked (via categories) + external payments
- `getProjectEntries()`: Merges transactions + external payments, sorted by date
- `getProjectTimeSeries()`: Monthly bars (tracked/external/total) + cumulative line
- Respects project date range (startDate/endDate) for filtering

**AttachmentStorageService** - Manages file uploads
- Stores receipts for external payments in `public/uploads/external_payments/`
- Validates file types (PDF, JPG, PNG) and size (max 10MB)
- Generates unique filenames to prevent collisions

**Key architectural patterns:**
1. **Money PHP Library**: All financial calculations use the Money PHP library (via `MoneyFactory`) for precision - NEVER use floats for money
2. **Service Layer**: Business logic lives in Services, Controllers should be thin
3. **Repository Pattern**: Custom query logic goes in repositories, not directly in services
4. **DTO Pattern**: API contracts use DTOs, not raw entities
5. **No TransactionType Constraint**: Categories can now contain both CREDIT and DEBIT transactions (recent architectural change)

### Frontend Architecture (React + TypeScript)

The frontend uses a **domain-based architecture** mirroring backend domains:

```
frontend/src/
├── domains/           # Domain modules (accounts, categories, transactions, etc.)
│   ├── transactions/
│   │   ├── components/    # Domain-specific components
│   │   ├── hooks/         # Domain-specific hooks
│   │   ├── models/        # TypeScript types/interfaces
│   │   ├── services/      # API service calls (if needed)
│   │   ├── utils/         # Domain utilities
│   │   ├── TransactionPage.tsx
│   │   └── index.tsx
│   └── [other domains...]
├── shared/            # Shared across domains
│   ├── components/    # Reusable UI components
│   ├── hooks/         # Reusable hooks
│   └── utils/         # Shared utilities
├── app/               # App-level configuration
├── lib/               # Core libraries (e.g., api.ts for API calls)
├── components/        # Global layout components
└── App.tsx            # Main application component
```

**Key patterns:**
1. **API Communication**: Uses `fetch` via functions in `lib/api.ts` (can be extended per domain)
2. **UI Components**: Built with Radix UI primitives + Tailwind CSS
3. **State Management**: React hooks (useState, useEffect) - no global state library
4. **Feature Flags**: FeatureFlagContext provides runtime feature toggles via `useFeatureFlag()` hook
5. **Routing**: React Router DOM for navigation
6. **Toast Notifications**: react-hot-toast for user feedback
7. **Charts**: Recharts library for data visualization, react-sparklines for mini-charts

#### New Dashboard Components (Adaptive Dashboard)

**domains/dashboard/components/**
- `ActiveBudgetsGrid.tsx` - Grid layout for active EXPENSE/INCOME budgets
- `BehavioralInsightsPanel.tsx` - Displays top 3 behavioral insights with neutral messaging
- `OlderBudgetsPanel.tsx` - Collapsible panel for inactive budgets
- `BudgetInsightBadge.tsx` - Visual indicator for insight level (stable/slight/anomaly)
- `SparklineChart.tsx` - Mini 6-month trend visualization

**domains/budgets/components/**
- `ProjectsSection.tsx` - Main projects overview with active/completed tabs
- `ProjectCard.tsx` - Individual project card with progress indicator
- `ProjectDetailView.tsx` - Detailed project view with time-series chart
- `ProjectCreateForm.tsx` - Modal form for creating new projects
- `ExternalPaymentForm.tsx` - Form for adding external payments with file upload
- `ExternalPaymentsList.tsx` - List of external payments with attachment links

**domains/budgets/services/**
- `AdaptiveDashboardService.ts` - API calls for active/older budgets and projects
  - `fetchActiveBudgets(months?: number): Promise<ActiveBudget[]>`
  - `fetchOlderBudgets(months?: number): Promise<OlderBudget[]>`
  - `fetchProjects(status?: string): Promise<ProjectDetails[]>`
  - `createProject(data: CreateProjectRequest): Promise<ProjectDetails>`
  - `addExternalPayment(budgetId: number, formData: FormData): Promise<void>`

**domains/budgets/models/**
- `AdaptiveBudget.ts` - TypeScript interfaces:
  - `ActiveBudget` - Budget with insights and sparkline data
  - `OlderBudget` - Simplified budget data for inactive budgets
  - `ProjectDetails` - Full project data with aggregated totals
  - `BehavioralInsight` - Insight level, message, deltaPercent, sparklineData

**shared/contexts/**
- `FeatureFlagContext.tsx` - Provides feature flag state to entire app
  - Fetches flags from `/api/feature-flags` on mount
  - Exposes `useFeatureFlag(name: string): boolean` hook
  - Caches flags in React context for performance

### Database

- **ORM**: Doctrine ORM with migrations
- **Migration workflow**: Generate migration → Review → Run via docker exec
- **Test database**: Separate `money_test` database for PHPUnit tests
- **Time zone**: All timestamps stored in UTC (`command: --default-time-zone='+00:00'`)

## Testing

### Backend Tests

Located in `backend/tests/`:
- **Unit/** - Unit tests for services and utilities
- **Integration/** - Integration tests with database
- **Fixtures/** - Test data fixtures
- **TestCase/** - Base test case classes

Run via PHPUnit inside container (see Backend Commands above).

## Important Money Handling

**Always use Money PHP library for financial calculations**:

```php
// CORRECT:
use App\Money\MoneyFactory;
$money = $this->moneyFactory->create($amount);

// WRONG:
$total = $amount1 + $amount2; // Never use float arithmetic
```

Frontend receives money as strings with decimal precision from API, display as-is or format with `toLocaleString()`.

## Git Workflow

**Existing branches:**
- **Main branch**: `main` (production, deployed to NAS)
- **Development branch**: `develop` (staging)

**Branch naming conventions** (create as needed):
- **Feature branches**: `feature/*` (e.g., `feature/add-authentication`, `feature/budget-tests`)
- **Hotfix branches**: `hotfix/*` (e.g., `hotfix/fix-csv-import`)

**Standard workflow:**
1. Create feature branch from `develop`: `git checkout -b feature/my-feature`
2. Make changes and test locally
3. Commit changes: `git commit -m "Description of changes"`
4. Push to remote: `git push -u origin feature/my-feature`
5. Create PR to `develop` (via GitHub/GitLab/etc.)
6. After approval, merge to `develop`
7. Delete feature branch after merge
8. When ready for release, merge `develop` to `main`

**Current branch:** You are on `develop`

## Deployment

- **Development**: Local via docker-compose.yml
- **Production**: Synology NAS via docker-compose.prod.yml
- **Deployment scripts**: Located in `synology/` directory
- **Backups**: Automated daily backups via Synology Task Scheduler (see README.md)

## API Documentation

OpenAPI/Swagger documentation available at:
- Development: http://localhost:8686/api/doc
- Uses `nelmio/api-doc-bundle` with annotations in Controllers

### Key API Endpoints

#### Budget Endpoints

**GET /api/budgets/active**
- Fetches active budgets (EXPENSE/INCOME with recent activity, or PROJECTS with active status)
- Query params:
  - `months` (optional, default: 2): Number of months to look back for activity
  - `budgetType` (optional): Filter by BudgetType (EXPENSE, INCOME, PROJECT)
- Response includes behavioral insights (if feature flag enabled)
```json
[
  {
    "id": 1,
    "name": "Groceries",
    "budgetType": "EXPENSE",
    "amount": "500.00",
    "spent": "450.00",
    "remaining": "50.00",
    "percentageUsed": 90.0,
    "insight": {
      "level": "slight",
      "message": "Iets hoger dan normaal",
      "deltaPercent": 12.5,
      "sparklineData": [300.0, 320.0, 310.0, 330.0, 340.0, 450.0]
    }
  }
]
```

**GET /api/budgets/older**
- Fetches inactive budgets (budgets without recent transactions)
- Query params:
  - `months` (optional, default: 2): Cutoff for determining "older"
- Returns simplified budget data without insights

**POST /api/budgets**
- Creates a new budget
- Body:
```json
{
  "name": "Kitchen Renovation",
  "budgetType": "PROJECT",
  "amount": "15000.00",
  "startDate": "2025-01-01",
  "endDate": "2025-12-31",
  "status": "ACTIVE"
}
```

**GET /api/budgets/{id}/details**
- Fetches detailed project information (for PROJECT budgets)
- Response includes:
  - Tracked total (from categorized transactions)
  - External payments total
  - Category breakdown
  - Recent entries (merged transactions + external payments)

**POST /api/budgets/{id}/external-payments**
- Adds an external payment to a project
- Multipart form data:
  - `amount`: Decimal string (e.g., "1500.00")
  - `paidOn`: Date string (YYYY-MM-DD)
  - `payerSource`: Enum (MORTGAGE_DEPOT, INSURER, EMPLOYER, FAMILY, INHERITANCE, OTHER)
  - `note`: Description text
  - `attachment`: File upload (optional, PDF/JPG/PNG, max 10MB)

**GET /api/budgets/{id}/time-series**
- Fetches time series data for project visualization
- Response:
```json
{
  "monthlyBars": [
    {"month": "2025-01", "tracked": 1200.0, "external": 500.0, "total": 1700.0},
    {"month": "2025-02", "tracked": 800.0, "external": 0.0, "total": 800.0}
  ],
  "cumulativeLine": [
    {"month": "2025-01", "cumulative": 1700.0},
    {"month": "2025-02", "cumulative": 2500.0}
  ]
}
```

#### Feature Flag Endpoints

**GET /api/feature-flags**
- Lists all feature flags with their current state
- Response:
```json
[
  {"name": "living_dashboard", "isEnabled": true},
  {"name": "projects", "isEnabled": true},
  {"name": "behavioral_insights", "isEnabled": false}
]
```

## Common Patterns

### Adding a New Feature to Existing Domain

1. Add business logic to appropriate Service
2. Update Controller with new endpoint
3. Create/update DTOs for request/response
4. Update Mapper if entity changes
5. Add frontend API call in `lib/api.ts` or domain service
6. Create/update React components in domain
7. Write tests (backend PHPUnit, frontend manual)

### Creating a New Entity

1. Generate entity: `docker exec money-backend php bin/console make:entity`
2. Review generated entity in `src/Entity/`
3. Generate migration: `docker exec money-backend php bin/console doctrine:migrations:generate`
4. Review migration file in `migrations/`
5. Run migration: `docker exec money-backend php bin/console doctrine:migrations:migrate`

### Adding Auto-Categorization Pattern

Patterns use string matching on transaction descriptions. Pattern entities are linked to Categories and automatically assign transactions during import.

## Environment Configuration

- Backend uses `.env` and `.env.dev` (dev) or `.env.prod` (production)
- Frontend uses Vite environment variables (`VITE_API_URL`)
- CORS is configured in backend via `nelmio/cors-bundle`
- See `.env.example` and README.md for full configuration details

### Feature Flags Configuration

Feature flags enable/disable features at runtime without code deployment. They are stored in the database and can be overridden via environment variables.

**Environment Variables** (in `.env`):
```env
# Adaptive Dashboard with behavioral insights
MUNNEY_FEATURE_LIVING_DASHBOARD=true

# Projects functionality (PROJECT budgets + external payments)
MUNNEY_FEATURE_PROJECTS=true

# Behavioral insights on budget cards
MUNNEY_FEATURE_BEHAVIORAL_INSIGHTS=true

# Active budget lookback window (default: 2 months)
MUNNEY_ACTIVE_BUDGET_MONTHS=2
```

**Database Management**:
Feature flags are seeded during migration (`Version20250101000003.php`) with default values. To update flags at runtime:

```bash
# Via Symfony console (manual toggle)
docker exec money-backend php bin/console doctrine:query:sql \
  "UPDATE feature_flag SET is_enabled = 1 WHERE name = 'living_dashboard'"

# Or via API (GET /api/feature-flags to view)
```

**Frontend Usage**:
```typescript
import { useFeatureFlag } from '../../shared/contexts/FeatureFlagContext';

function MyComponent() {
  const livingDashboardEnabled = useFeatureFlag('living_dashboard');
  const projectsEnabled = useFeatureFlag('projects');

  if (!livingDashboardEnabled) {
    return <LegacyDashboard />;
  }

  return (
    <>
      <AdaptiveDashboard />
      {projectsEnabled && <ProjectsSection />}
    </>
  );
}
```

**Backend Usage**:
```php
use App\FeatureFlag\Service\FeatureFlagService;

public function __construct(
    private readonly FeatureFlagService $featureFlagService
) {}

public function myAction(): Response
{
    if ($this->featureFlagService->isEnabled('living_dashboard')) {
        // New adaptive dashboard logic
    } else {
        // Legacy dashboard logic
    }
}
```

## Code Style

- **Backend**: PSR-12 coding standards
- **Frontend**: ESLint configuration in `frontend/eslint.config.js`
- **Dutch comments**: Most inline comments and documentation are in Dutch (this is intentional)

## Known Technical Debt & Improvement Opportunities

### Critical Priority (Security)
1. **No Authentication/Authorization**: The application currently has no authentication system. All API endpoints are publicly accessible without any user verification. CORS is set to wildcard `*` allowing any origin.
   - `config/packages/security.yaml` has all access control commented out
   - No middleware validates that users own the accounts they're accessing
   - Recommendation: Implement JWT or session-based authentication before production deployment

### High Priority (Testing)
2. **Missing Test Coverage**:
   - **Budget Domain**: ✅ Now has good coverage (35 tests) for ActiveBudgetService and BudgetInsightsService
   - **Pattern Domain**: No tests (0% coverage) - SQL generation and pattern matching untested
   - **AI Services**: No tests for `AiPatternDiscoveryService` or `AiCategorizationService`
   - **Budget API**: No integration tests for new endpoints (/active, /older, external payments)
   - Recommendation: Prioritize Pattern domain and Budget API integration tests

### Medium Priority (Code Quality)
3. **Code Duplication**: Entity lookup pattern repeated 40+ times across services
   ```php
   $entity = $this->repository->find($id);
   if (!$entity) { throw new NotFoundHttpException('...'); }
   ```
   - Recommendation: Create `EntityLookupTrait` or base service helper method

4. **Large Files**: Several files exceed 500 lines
   - `TransactionRepository.php` (664 lines)
   - `TransactionService.php` (555 lines) - statistics methods should be extracted
   - `TransactionImportService.php` (517 lines)
   - Recommendation: Split into smaller, focused classes

5. **API Inconsistencies**:
   - Mixed use of verbs in URLs (`/create`, `/import`) instead of HTTP methods
   - Inconsistent error response formats (`errors` vs `error`)
   - Mix of Dutch and English error messages
   - Recommendation: Standardize REST conventions across all controllers

### Low Priority (Cleanup)
6. **Constructor Pattern Inconsistency**: Mix of PHP 8.1 property promotion and traditional property assignment
7. **Validation Error Handling**: Duplicated validation logic in 14+ controllers
8. **CORS Configuration**: Manual CORS headers in `ApiExceptionListener` duplicate `nelmio_cors` bundle config

## Testing Guide

### Running Tests
```bash
# Run all tests
docker exec money-backend vendor/bin/phpunit

# Run specific test file
docker exec money-backend vendor/bin/phpunit tests/Unit/Money/MoneyFactoryTest.php

# Run specific test method
docker exec money-backend vendor/bin/phpunit --filter testSetCategoryUpdatesTransaction
```

### Test Structure
- **ApiTestCase**: Base class for API integration tests (provides `makeJsonRequest`, `assertJsonResponse`)
- **DatabaseTestCase**: Base class for repository tests (handles database setup/teardown)
- **WebTestCase**: Base class for web tests

### Current Test Coverage (as of January 2025)
- **Account**: ✅ Good (10 API tests)
- **Category**: ✅ Good (16 API tests)
- **SavingsAccount**: ✅ Good (12 API tests)
- **Transaction**: ✅ Excellent (22 API tests + 3 repository tests + 3 unit tests)
- **MoneyFactory**: ✅ Good (4 unit tests)
- **Budget**: ✅ Good (21 ActiveBudgetService tests + 14 BudgetInsightsService tests)
- **Pattern**: ❌ None
- **AI Services**: ❌ None

Total: 112 tests, 496 assertions (as of latest run)

### Test Patterns and Examples

**DatabaseTestCase Usage**: For testing services that interact with the database

```php
use App\Tests\TestCase\DatabaseTestCase;

class ActiveBudgetServiceTest extends DatabaseTestCase
{
    private ActiveBudgetService $activeBudgetService;
    private Account $testAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activeBudgetService = $this->container->get(ActiveBudgetService::class);

        // Create test account
        $this->testAccount = new Account();
        $this->testAccount->setName('Test Account')
            ->setAccountNumber('NL91TEST' . uniqid())
            ->setIsDefault(true);
        $this->entityManager->persist($this->testAccount);
        $this->entityManager->flush();
    }

    public function testGetActiveBudgetsReturnsExpenseBudgetsWithRecentTransactions(): void
    {
        // Create EXPENSE budget with category
        $budget = $this->createBudget('Groceries', BudgetType::EXPENSE);
        $category = $this->createCategory('Food', $budget);

        // Add transaction from 1 month ago
        $this->createTransaction($category, -5000, new DateTimeImmutable('-1 month'));
        $this->entityManager->flush();

        // Get active budgets (default 2 months lookback)
        $activeBudgets = $this->activeBudgetService->getActiveBudgets();

        $this->assertCount(1, $activeBudgets);
        $this->assertEquals('Groceries', $activeBudgets[0]->getName());
    }

    // Helper methods for test data creation
    private function createBudget(string $name, BudgetType $type): Budget { /* ... */ }
    private function createCategory(string $name, Budget $budget): Category { /* ... */ }
    private function createTransaction(Category $category, int $amountInCents, DateTimeImmutable $date): Transaction { /* ... */ }
}
```

**Important Testing Notes**:
1. **Money Handling**: Always use `Money::EUR($cents)` and `setAmount(Money $money)`, never `setAmountInCents()`
2. **Bidirectional Relationships**: When creating Category-Budget relationships, ALWAYS set both sides:
   ```php
   $category->setBudget($budget);
   $budget->addCategory($category);  // Critical!
   ```
3. **DateTime vs DateTimeImmutable**: Transaction entity expects `DateTime`, convert with `DateTime::createFromImmutable()`
4. **Required Transaction Fields**: Always set hash, notes, balanceAfter, transactionCode, mutationType
5. **Doctrine DQL Limitations**: Use `SUBSTRING(date, 1, 7)` instead of `DATE_FORMAT(date, '%Y-%m')` for database compatibility

## Recent Architectural Changes

### Adaptive Dashboard Implementation (January 2025)
A major feature release implementing behavioral insights and project tracking:

**New Entities:**
- `ExternalPayment` - Off-ledger payments linked to PROJECT budgets
- `FeatureFlag` - Runtime feature toggles stored in database

**New Enums:**
- `BudgetType::PROJECT` - Added PROJECT type alongside EXPENSE/INCOME
- `ProjectStatus` - PLANNING, ACTIVE, ON_HOLD, COMPLETED, CANCELLED
- `PayerSource` - MORTGAGE_DEPOT, INSURER, EMPLOYER, FAMILY, INHERITANCE, OTHER

**New Services:**
- `ActiveBudgetService` - Classification of budgets as active/older based on transaction history
- `BudgetInsightsService` - Rolling median calculations with behavioral coaching
- `ProjectAggregatorService` - Aggregates tracked + external payments for projects
- `AttachmentStorageService` - File upload handling for payment receipts
- `FeatureFlagService` - Runtime feature flag management

**Behavioral Insights Algorithm:**
1. Computes "normal" baseline as rolling 6-month median of spending
2. Compares current month to baseline, calculates delta percentage
3. Classifies as: Stable (<10%), Slight (10-30%), Anomaly (≥30%)
4. Generates neutral Dutch coaching messages (no judgmental language)
5. Provides 6-month sparkline data for trend visualization

**Feature Flags:**
- `living_dashboard` - Enables adaptive dashboard with insights
- `projects` - Enables PROJECT budgets and external payments
- `behavioral_insights` - Shows insight badges on budget cards

**Migration Path:**
- Existing EXPENSE/INCOME budgets work unchanged
- Legacy dashboard remains available when `living_dashboard=false`
- Feature flags stored in database with env var fallback
- Migrations: Version20250101000001 (ExternalPayment), Version20250101000002 (Budget extensions), Version20250101000003 (FeatureFlag)

### Removal of TransactionType Constraint on Categories (2024-2025)
Previously, categories were strictly tied to either CREDIT or DEBIT transaction types. This constraint has been removed:
- Categories can now contain both CREDIT and DEBIT transactions
- The `transactionType` field has been removed from the Category entity
- Tests have been updated to reflect this change
- This allows more flexible categorization (e.g., a "Transfer" category can handle both incoming and outgoing transfers)

### Entity Relationships
- **Category → Account**: ManyToOne relationship (Category knows about Account)
- **Account ← Category**: No inverse relationship (Account doesn't track Categories collection)
  - This is intentional - unidirectional relationship is sufficient
  - Categories are queried via CategoryRepository when needed
- **Budget ↔ Category**: Bidirectional ManyToMany relationship
  - Budget has `getCategories()` collection
  - Category has `getBudget()` reference (nullable)
  - When creating test data, ALWAYS set both sides: `$category->setBudget($budget); $budget->addCategory($category);`
- **ExternalPayment → Budget**: ManyToOne relationship (payments belong to projects)
