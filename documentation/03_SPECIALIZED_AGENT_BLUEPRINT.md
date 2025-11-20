# SPECIALIZED AGENT BLUEPRINT
## Mister Munney Development Assistant

**Date:** November 20, 2025
**Purpose:** Create a context-aware Claude Code agent for Mister Munney development
**Based On:** Comprehensive application audit findings

---

## 🎯 AGENT PURPOSE

This specialized agent is designed to assist developers working on the Mister Munney personal finance application by:

1. **Preventing Security Issues** - Stop secrets from being committed, enforce secret management
2. **Guiding Deployments** - Ensure proper deployment procedures and environment management
3. **Maintaining Code Quality** - Enforce architectural patterns and best practices
4. **Catching Common Mistakes** - Identify configuration issues before they cause problems
5. **Accelerating Development** - Provide context-aware assistance based on deep application knowledge

---

## 🏗️ APPLICATION ARCHITECTURE KNOWLEDGE

### Technology Stack

**Backend: Symfony 7.2 (PHP 8.2+)**
- Domain-Driven Design with bounded contexts
- Doctrine ORM for database access
- JWT authentication via lexik/jwt-authentication-bundle
- OpenAPI/Swagger documentation
- Rate limiting and security middleware
- Event listeners for cross-cutting concerns

**Frontend: React 19 (TypeScript)**
- Domain-based component organization
- React Router for navigation
- Axios for API communication
- Tailwind CSS + Radix UI components
- Vite build system

**Database: MySQL 8.0**
- 19 migrations (as of Nov 13, 2025)
- 12+ tables for financial data
- Foreign keys and indexes properly configured
- UTC timezone for all timestamps

**Infrastructure:**
- Docker Compose for all environments
- Traefik reverse proxy for production
- Self-hosted GitHub Actions runner
- Ubuntu 22.04 production/dev servers

### Domain Structure

The application follows Domain-Driven Design with these bounded contexts:

#### 1. **Account Domain** (`backend/src/Account/`, `frontend/src/domains/accounts/`)
- Represents financial accounts (bank accounts, credit cards)
- Entities: `Account`, `AccountUser` (many-to-many relationship)
- Features: Multi-user account sharing with ownership tracking
- Security: Account ownership verification required for all operations

#### 2. **Transaction Domain** (`backend/src/Transaction/`)
- Core financial transaction management
- Entities: `Transaction` (supports parent-child relationships for splits)
- Features: CSV import, AI categorization, transaction splitting
- Services: `TransactionService`, `AiCategorizationService`

#### 3. **Category Domain** (`backend/src/Category/`)
- Transaction categorization
- Entities: `Category` (hierarchical)
- Features: Category merge, statistics, icon management

#### 4. **Budget Domain** (`backend/src/Budget/`)
- Budget management and tracking
- Entities: `Budget`, `ExternalPayment`, `ProjectAttachment`
- Features: Adaptive dashboard, project tracking, file attachments
- Services: `BudgetService`, `BudgetInsightsService`, `ProjectAggregatorService`

#### 5. **Pattern Domain** (`backend/src/Pattern/`)
- Transaction pattern matching for auto-categorization
- Entities: `Pattern`, `AiPatternSuggestion`
- Features: Rule-based matching, AI pattern discovery
- Services: `PatternService`, `AiPatternDiscoveryService`

#### 6. **Security Domain** (`backend/src/Security/`)
- Authentication and security features
- Services: `AccountLockService`, `LoginAttemptService`, `CaptchaService`
- Features: JWT authentication, account locking, rate limiting, CAPTCHA

#### 7. **User Domain** (`backend/src/User/`)
- User management and authentication
- Entity: `User` (implements Symfony UserInterface)
- Controllers: `AuthController` for login/register
- Features: Argon2id password hashing, account relationships

### Key Architectural Patterns

**Repository Pattern:**
- All entities have dedicated repositories
- Custom query methods in repositories, not services
- Example: `TransactionRepository::findByFilter()`

**DTO Pattern:**
- Request/response data transfer objects
- Located in `{Domain}/DTO/` directories
- Validation via Symfony Validator constraints
- Example: `CreateTransactionDTO`, `BudgetSummaryDTO`

**Service Layer:**
- Business logic in services, not controllers
- Services are readonly classes (PHP 8.2+)
- Dependency injection via constructor
- Example: `BudgetService`, `AccountService`

**Mapper Pattern:**
- Convert between entities and DTOs
- Located in `{Domain}/Mapper/` directories
- Bidirectional mapping (toDto, toEntity)
- Example: `TransactionMapper`, `AccountMapper`

**Event Listeners:**
- Cross-cutting concerns handled via events
- Rate limiting: `RateLimitListener`, `LoginRateLimitListener`
- Security: `LoginAttemptListener`
- API exceptions: `ApiExceptionListener`

---

## 🚨 CRITICAL PITFALLS TO PREVENT

### 1. SECRET MANAGEMENT VIOLATIONS

**NEVER ALLOW:**
- Hardcoded API keys in any file
- Passwords in docker-compose files
- Secrets in environment files that are tracked in git
- JWT passphrases in configuration files

**ENFORCE:**
- All secrets must use environment variables
- `.env` and `.env.local` files must be in `.gitignore`
- Docker secrets for production deployments
- Secret rotation procedures documented

**Red Flags to Catch:**
```php
// ❌ STOP THIS
$apiKey = 'sk-proj-abc123...';
$password = 'hardcoded-password';

// ❌ STOP THIS
$this->mailer->send($email, 'api-key-here');

// ✅ CORRECT
$apiKey = $_ENV['OPENAI_API_KEY'];
$password = $this->getParameter('database.password');
```

```yaml
# ❌ STOP THIS
environment:
  OPENAI_API_KEY: "sk-proj-abc123..."
  JWT_PASSPHRASE: "my-secret-phrase"

# ✅ CORRECT
environment:
  OPENAI_API_KEY: ${OPENAI_API_KEY}
  JWT_PASSPHRASE: ${JWT_PASSPHRASE}
```

### 2. DEPLOYMENT CONFIGURATION MISTAKES

**Environment Variable Naming:**
- **Dev:** Use `_DEV` suffix (e.g., `MYSQL_PASSWORD_DEV`)
- **Prod:** Use `_PROD` suffix (e.g., `MYSQL_PASSWORD_PROD`)
- **Local:** No suffix needed

**Required Environment Variables:**

**ALL Environments:**
- `MYSQL_PASSWORD` (or `_DEV`/`_PROD`)
- `MYSQL_ROOT_PASSWORD` (or `_DEV`/`_PROD`)
- `APP_SECRET` (or `_DEV`/`_PROD`)
- `JWT_PASSPHRASE` (or `_DEV`/`_PROD`)

**Production Only:**
- `OPENAI_API_KEY`
- `MAILER_DSN` (Resend API)
- `HCAPTCHA_SECRET_KEY` ⚠️ CRITICAL - often forgotten!
- `HCAPTCHA_SITE_KEY`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`
- `APP_URL`

**Docker Compose File Usage:**
- Local dev: `docker-compose.yml`
- Dev server: `deploy/ubuntu/docker-compose.dev.yml`
- Prod server: `deploy/ubuntu/docker-compose.prod.yml`
- Root `docker-compose.prod.yml`: NOT USED (should be deleted)

### 3. SECURITY FEATURE INCOMPLETENESS

**When Adding Security Features:**

✅ **Complete Checklist:**
- [ ] Add environment variable to all environments (.env.dev, .env.prod)
- [ ] Add to services.yaml if service needs it
- [ ] Add to .env.example with placeholder
- [ ] Document in deployment guide
- [ ] Test in dev environment first
- [ ] Verify in production after deployment

**Example: hCaptcha Integration**

Previous mistake:
- Added `CaptchaService` requiring `HCAPTCHA_SECRET_KEY`
- Added to services.yaml
- Added to local `.env`
- ❌ Forgot to add to `/srv/munney-prod/.env`
- ❌ Forgot to add to `/srv/munney-dev/.env`
- Result: CAPTCHA broken in all deployed environments

**Prevent This:**
```bash
# Always verify environment variables in all environments
grep "HCAPTCHA_SECRET_KEY" backend/.env
ssh lars@192.168.0.105 "grep HCAPTCHA_SECRET_KEY /srv/munney-dev/.env"
ssh lars@192.168.0.105 "grep HCAPTCHA_SECRET_KEY /srv/munney-prod/.env"
```

### 4. DEPLOYMENT WORKFLOW GAPS

**Dev Deployment Must Include:**
1. Pull latest code
2. Generate JWT keys (if missing)
3. Build images
4. Restart containers
5. **Run migrations** ⚠️ Currently missing!
6. Clear cache
7. Health check

**Production Deployment Must Include:**
1. Create database backup
2. Pull latest code
3. Generate JWT keys (if missing)
4. Build images (with --no-cache)
5. Restart containers
6. Run migrations
7. Clear cache
8. Health check
9. Verify deployment

**Currently Missing in Dev:**
```yaml
# Add after container restart in .github/workflows/deploy-dev.yml
- name: 🗄️ Run database migrations
  run: |
    cd /srv/munney-dev
    docker exec munney-dev-backend php bin/console doctrine:migrations:migrate --no-interaction --env=dev
```

### 5. AUTHENTICATION AND AUTHORIZATION PATTERNS

**Account Ownership Verification:**

Every endpoint that accesses account data must verify ownership:
```php
// ✅ CORRECT Pattern
#[Route('/api/accounts/{accountId}/transactions', methods: ['GET'])]
public function getTransactions(int $accountId): JsonResponse
{
    $account = $this->accountRepository->find($accountId);

    if (!$account) {
        throw new NotFoundHttpException('Account not found');
    }

    // SECURITY: Verify user owns this account
    $user = $this->getUser();
    if (!$user->hasAccessToAccount($account)) {
        throw new AccessDeniedHttpException('Access denied');
    }

    // Proceed with business logic...
}
```

**User Entity Methods:**
- `User::ownsAccount($account)`: Check if user is owner
- `User::hasAccessToAccount($account)`: Check if user has any access (owner or shared)
- `User::getAccounts()`: Get all accounts user has access to

**AccountUser Relationship:**
- Manages many-to-many relationship between User and Account
- Tracks ownership status: `isOwner()`
- Tracks active status: `isActive()`
- Never directly manipulate - use `AccountSharingService`

---

## 📝 CODE QUALITY GUIDELINES

### Naming Conventions

**Entities:**
- Singular nouns: `Transaction`, `Budget`, `Category`
- Located in `{Domain}/Entity/` or `Entity/` if shared

**Services:**
- Descriptive names ending in "Service": `BudgetService`, `TransactionService`
- Located in `{Domain}/Service/`
- Readonly classes: `readonly class BudgetService`

**Controllers:**
- Domain name + "Controller": `BudgetController`, `TransactionController`
- Located in `{Domain}/Controller/`
- Extend `AbstractController`

**Repositories:**
- Entity name + "Repository": `TransactionRepository`
- Located in `{Domain}/Repository/` or `Entity/Repository/`

**DTOs:**
- Purpose + DTO suffix: `CreateBudgetDTO`, `BudgetSummaryDTO`
- Located in `{Domain}/DTO/`

**Mappers:**
- Entity name + "Mapper": `TransactionMapper`
- Located in `{Domain}/Mapper/`

### Controller Best Practices

**Standard Pattern:**
```php
#[Route('/api/budgets', methods: ['POST'])]
#[OA\Post(...)] // Always include OpenAPI documentation
public function createBudget(Request $request): JsonResponse
{
    // 1. Parse request
    $data = json_decode($request->getContent(), true);

    // 2. Create and validate DTO
    $dto = new CreateBudgetDTO($data);
    $errors = $this->validator->validate($dto);

    if (count($errors) > 0) {
        return $this->json([
            'errors' => array_map(fn($e) => $e->getMessage(), iterator_to_array($errors))
        ], Response::HTTP_BAD_REQUEST);
    }

    // 3. Verify ownership (if applicable)
    $account = $this->accountRepository->find($dto->accountId);
    if (!$this->getUser()->hasAccessToAccount($account)) {
        throw new AccessDeniedHttpException();
    }

    // 4. Delegate to service
    $budget = $this->budgetService->createBudget($dto);

    // 5. Return response
    return $this->json(
        $this->budgetMapper->toDto($budget),
        Response::HTTP_CREATED
    );
}
```

### Service Best Practices

**Responsibilities:**
- Business logic only (no HTTP concerns)
- Transaction management if needed
- Delegate to repositories for data access
- Use mappers for entity/DTO conversion

**Pattern:**
```php
readonly class BudgetService
{
    public function __construct(
        private BudgetRepository $budgetRepository,
        private AccountRepository $accountRepository,
        private EntityManagerInterface $entityManager,
        private BudgetMapper $budgetMapper
    ) {}

    public function createBudget(CreateBudgetDTO $dto): Budget
    {
        // 1. Load related entities
        $account = $this->accountRepository->find($dto->accountId);

        if (!$account) {
            throw new \InvalidArgumentException('Account not found');
        }

        // 2. Create entity
        $budget = $this->budgetMapper->toEntity($dto);
        $budget->setAccount($account);

        // 3. Persist
        $this->entityManager->persist($budget);
        $this->entityManager->flush();

        return $budget;
    }
}
```

### Migration Best Practices

**Always:**
- Use Doctrine migrations: `php bin/console make:migration`
- Never skip migrations in production
- Test migrations on dev first
- Include rollback (`down()` method)
- Add descriptive `getDescription()`

**Migration Checklist:**
- [ ] Migration created: `php bin/console make:migration`
- [ ] Description added to migration class
- [ ] Migration tested locally: `php bin/console doctrine:migrations:migrate`
- [ ] Rollback tested: `php bin/console doctrine:migrations:migrate prev`
- [ ] Migration committed to git
- [ ] Deployed to dev and verified
- [ ] Deployed to prod and verified

**Never:**
- Modify existing migrations (create new one instead)
- Run raw SQL without migration
- Skip migrations in deployment

---

## 🎯 SPECIALIZED ASSISTANCE SCENARIOS

### Scenario 1: Adding a New API Endpoint

**Agent Checklist:**
1. ✅ Is endpoint in appropriate domain controller?
2. ✅ Does it have OpenAPI documentation?
3. ✅ Does it verify account ownership if accessing account data?
4. ✅ Does it use DTOs for request/response?
5. ✅ Does it validate input?
6. ✅ Does it delegate business logic to service?
7. ✅ Does it return appropriate HTTP status codes?

**Remind Developer:**
- Add route attribute with method: `#[Route('/api/path', methods: ['GET'])]`
- Add OpenAPI documentation: `#[OA\Get(...)]`
- Use proper HTTP status codes: 200 (OK), 201 (Created), 400 (Bad Request), 404 (Not Found)

### Scenario 2: Adding a New Environment Variable

**Agent Checklist:**
1. ✅ Is variable added to `.env.example` with placeholder?
2. ✅ Is variable added to local `.env` or `.env.local`?
3. ✅ Is variable added to `/srv/munney-dev/.env` on server?
4. ✅ Is variable added to `/srv/munney-prod/.env` on server?
5. ✅ Is variable documented in deployment guide?
6. ✅ If it's a secret, is it in `.gitignore`?

**Remind Developer:**
- Never commit actual secrets
- Use descriptive names: `SERVICE_API_KEY` not `API_KEY`
- Add to `deploy/ubuntu/.env.dev.example` and `.env.prod.example`

### Scenario 3: Creating a Database Migration

**Agent Checklist:**
1. ✅ Was migration generated with `make:migration` command?
2. ✅ Does migration have descriptive class name and `getDescription()`?
3. ✅ Does `down()` method properly reverse `up()` method?
4. ✅ Has migration been tested locally?
5. ✅ Has rollback been tested?
6. ✅ Is migration needed in dev deployment workflow?

**Remind Developer:**
```bash
# Generate migration
php bin/console make:migration

# Review migration file
# Add getDescription() if not present

# Test migration
php bin/console doctrine:migrations:migrate

# Test rollback
php bin/console doctrine:migrations:migrate prev

# Re-apply
php bin/console doctrine:migrations:migrate
```

### Scenario 4: Deploying to Production

**Agent Pre-Flight Checklist:**
1. ✅ All tests passing?
2. ✅ Migrations tested on dev?
3. ✅ Environment variables documented?
4. ✅ No hardcoded secrets in code?
5. ✅ Database backup will be created automatically?
6. ✅ Rollback procedure documented?
7. ✅ Team notified of deployment?

**Remind Developer:**
- Deployment creates automatic backup
- Monitor logs after deployment
- Test critical user flows
- Have rollback procedure ready

### Scenario 5: Debugging CAPTCHA Not Working

**Agent Diagnostic Steps:**
1. ✅ Check if `HCAPTCHA_SECRET_KEY` is in environment:
   ```bash
   docker exec munney-prod-backend printenv | grep HCAPTCHA
   ```
2. ✅ Check if `CaptchaService` is registered in services.yaml
3. ✅ Check browser console for CAPTCHA errors
4. ✅ Verify CAPTCHA is triggered after 3 failed attempts
5. ✅ Check backend logs for hCaptcha API errors

**Common Causes:**
- Missing `HCAPTCHA_SECRET_KEY` in production (most common!)
- Incorrect API key
- hCaptcha API rate limiting
- CORS blocking hCaptcha widget

---

## 🔍 PROACTIVE CODE REVIEW PATTERNS

### When Reviewing Pull Requests

**Security Checklist:**
- [ ] No hardcoded secrets
- [ ] No passwords in configuration
- [ ] Environment variables used for sensitive data
- [ ] `.env` files not committed (check `.gitignore`)
- [ ] Account ownership verified in endpoints
- [ ] Input validation present
- [ ] SQL injection prevention (use parameterized queries)

**Architecture Checklist:**
- [ ] Code in correct domain
- [ ] Business logic in services, not controllers
- [ ] DTOs used for API boundaries
- [ ] Mappers used for entity/DTO conversion
- [ ] Repositories used for data access
- [ ] Proper dependency injection

**Database Checklist:**
- [ ] Migrations generated properly
- [ ] Rollback method implemented
- [ ] Foreign keys defined
- [ ] Indexes added for queried columns
- [ ] No raw SQL (use Doctrine DQL)

**Testing Checklist:**
- [ ] Unit tests for services
- [ ] Integration tests for controllers
- [ ] Test coverage > 65% (target: 85%)

**Documentation Checklist:**
- [ ] OpenAPI documentation for endpoints
- [ ] PHPDoc comments for complex methods
- [ ] README updated if architecture changed
- [ ] Environment variables documented

---

## 🚀 QUICK REFERENCE COMMANDS

### Local Development

```bash
# Start all containers
docker compose up -d

# Stop all containers
docker compose down

# View logs
docker logs money-backend -f
docker logs money-frontend -f

# Run migrations
docker exec money-backend php bin/console doctrine:migrations:migrate

# Clear cache
docker exec money-backend php bin/console cache:clear

# Create migration
docker exec money-backend php bin/console make:migration

# Run tests
docker exec money-backend php bin/phpunit
```

### Server Operations

```bash
# SSH to server
ssh lars@192.168.0.105

# Check running containers
docker ps --filter 'label=project=munney'

# View production logs
docker logs munney-prod-backend --tail 100 -f

# Run migrations on production
docker exec munney-prod-backend php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Check environment variables
docker exec munney-prod-backend printenv | grep HCAPTCHA

# Restart production containers
cd /srv/munney-prod
docker compose -f deploy/ubuntu/docker-compose.prod.yml restart
```

### Database Operations

```bash
# Access database
docker exec -it money-mysql mysql -u money -p money_db

# Create backup
docker exec money-mysql mysqldump -u root -p money_db > backup_$(date +%Y%m%d).sql

# Restore backup
docker exec -i money-mysql mysql -u root -p money_db < backup_20251120.sql

# Check migration status
docker exec money-backend php bin/console doctrine:migrations:status
```

---

## 📚 KEY FILES AND LOCATIONS

### Configuration Files
- `backend/config/services.yaml` - Service container configuration
- `backend/config/packages/security.yaml` - Authentication config
- `backend/config/packages/lexik_jwt_authentication.yaml` - JWT config
- `backend/config/packages/nelmio_cors.yaml` - CORS config
- `backend/config/routes.yaml` - API routes

### Environment Files
- `.env.example` - Template (tracked in git)
- `.env.local` - Local overrides (gitignored)
- `backend/.env` - Backend environment (gitignored)
- `deploy/ubuntu/.env.dev.example` - Dev template
- `deploy/ubuntu/.env.prod.example` - Prod template

### Docker Files
- `docker-compose.yml` - Local development
- `deploy/ubuntu/docker-compose.dev.yml` - Dev server
- `deploy/ubuntu/docker-compose.prod.yml` - Prod server
- `backend/Dockerfile` - Backend dev image
- `backend/Dockerfile.prod` - Backend prod image
- `frontend/Dockerfile` - Frontend dev image
- `frontend/Dockerfile.prod` - Frontend prod image

### Deployment Files
- `.github/workflows/deploy-dev.yml` - Dev auto-deploy
- `.github/workflows/deploy-prod.yml` - Prod auto-deploy

### Documentation
- `documentation/00_EXECUTIVE_SUMMARY.md` - Audit overview
- `documentation/01_SECURITY_AUDIT_DETAILED.md` - Security findings
- `documentation/02_DEPLOYMENT_PIPELINE_ANALYSIS.md` - Deployment analysis
- `documentation/03_SPECIALIZED_AGENT_BLUEPRINT.md` - This document

---

## 🎓 LEARNING RESOURCES

### Symfony Resources
- Symfony 7.2 Docs: https://symfony.com/doc/7.2/index.html
- Doctrine ORM: https://www.doctrine-project.org/projects/orm.html
- JWT Bundle: https://github.com/lexik/LexikJWTAuthenticationBundle

### Domain-Driven Design
- DDD in PHP: https://leanpub.com/ddd-in-php
- Bounded Contexts: Understanding domain separation

### Security
- OWASP Top 10: https://owasp.org/www-project-top-ten/
- JWT Best Practices: https://datatracker.ietf.org/doc/html/rfc8725

---

## 💡 AGENT PERSONALITY AND TONE

**When Assisting:**
- Be proactive about security issues
- Explain WHY, not just WHAT
- Reference specific file locations and line numbers
- Provide code examples
- Suggest testing steps
- Anticipate follow-up questions

**When Warning:**
- Be clear and direct about security risks
- Explain potential impact
- Provide immediate fix and long-term solution
- Don't sugarcoat critical issues

**When Guiding:**
- Break complex tasks into steps
- Verify each step before proceeding
- Provide rollback procedures
- Document decisions made

---

## ✅ AGENT SUCCESS CRITERIA

The agent is successful when it:

1. **Prevents Issues**
   - No secrets ever committed
   - All deployments include necessary steps
   - Environment variables consistent across environments

2. **Accelerates Development**
   - Developers spend less time debugging config issues
   - Deployment procedures are clear and reliable
   - Common patterns are reused correctly

3. **Maintains Quality**
   - Code follows architectural patterns
   - Security best practices enforced
   - Tests cover critical paths

4. **Builds Confidence**
   - Developers trust deployment process
   - Rollback procedures are clear
   - Security posture is strong

---

**Blueprint Status:** ✅ COMPLETE
**Version:** 1.0
**Last Updated:** November 20, 2025
**Maintainer:** Development Team
