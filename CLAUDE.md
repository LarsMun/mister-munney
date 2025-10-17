# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Munney is a personal finance management application built with Symfony 7.2 (PHP 8.3) and React 19 (TypeScript). It allows users to import bank transactions via CSV, auto-categorize them using pattern matching, track budgets, and visualize spending through interactive charts.

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
├── Budget/
├── Category/
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
- **Enum/** - Application-wide enums (e.g., `TransactionType`)
- **Money/** - Contains `MoneyFactory` for Money PHP library operations
- **Mapper/** - Contains `PayloadMapper` for common mapping operations

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
4. **Routing**: React Router DOM for navigation
5. **Toast Notifications**: react-hot-toast for user feedback
6. **Charts**: Recharts library for data visualization

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
   - **Budget Domain**: No tests (0% coverage) - complex financial calculations are untested
   - **Pattern Domain**: No tests (0% coverage) - SQL generation and pattern matching untested
   - **AI Services**: No tests for `AiPatternDiscoveryService` or `AiCategorizationService`
   - Recommendation: Prioritize Budget and Pattern domain tests as they contain critical business logic

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

### Current Test Coverage (as of 2025)
- **Account**: ✅ Good (10 API tests)
- **Category**: ✅ Good (16 API tests)
- **SavingsAccount**: ✅ Good (12 API tests)
- **Transaction**: ✅ Excellent (22 API tests + 3 repository tests + 3 unit tests)
- **MoneyFactory**: ✅ Good (4 unit tests)
- **Budget**: ❌ None
- **Pattern**: ❌ None
- **AI Services**: ❌ None

Total: 69 tests, 366 assertions (as of latest run)

## Recent Architectural Changes

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
