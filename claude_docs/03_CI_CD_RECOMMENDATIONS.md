# CI/CD Recommendations

**Last Updated:** January 19, 2026

> **Note:** Most of these recommendations have been implemented. See [02_CI_CD_ANALYSIS.md](02_CI_CD_ANALYSIS.md) for current status.

---

## Priority 1: Add Testing to Pipeline ✅ IMPLEMENTED

The CI pipeline (`ci.yml`) now includes:
- PHPUnit tests for backend
- TypeScript type checking
- ESLint validation
- Production build validation

### Original Recommendation (for reference)
Backend tests added to workflows:

```yaml
- name: Run backend tests
  run: |
    cd /srv/munney-dev
    docker exec munney-dev-backend php bin/console doctrine:database:create --env=test --if-not-exists || true
    docker exec munney-dev-backend php bin/console doctrine:migrations:migrate --env=test --no-interaction
    docker exec munney-dev-backend php bin/phpunit --testdox
```

### Frontend Build Validation
```yaml
- name: Validate frontend build
  run: |
    cd /srv/munney-dev/frontend
    npm ci
    npm run lint
    npm run build
```

## Priority 2: Add PR Validation Workflow ✅ IMPLEMENTED

The CI pipeline runs on all pull requests to develop and main branches.

### Original Recommendation (for reference)
Example workflow:

```yaml
name: PR Validation

on:
  pull_request:
    branches: [main, develop]

jobs:
  backend-tests:
    runs-on: self-hosted
    steps:
      - uses: actions/checkout@v4

      - name: Start test containers
        run: |
          docker compose -f docker-compose.test.yml up -d

      - name: Run PHPUnit
        run: |
          docker compose -f docker-compose.test.yml exec -T backend php bin/phpunit

      - name: Run PHPStan
        run: |
          docker compose -f docker-compose.test.yml exec -T backend vendor/bin/phpstan analyse

      - name: Cleanup
        if: always()
        run: docker compose -f docker-compose.test.yml down

  frontend-checks:
    runs-on: self-hosted
    steps:
      - uses: actions/checkout@v4

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
          cache-dependency-path: frontend/package-lock.json

      - name: Install dependencies
        run: cd frontend && npm ci

      - name: Lint
        run: cd frontend && npm run lint

      - name: Type check
        run: cd frontend && npx tsc --noEmit

      - name: Build
        run: cd frontend && npm run build
```

## Priority 3: Improve Deployment Safety ⚠️ PARTIALLY IMPLEMENTED

Database backups are created before production deployments. Rollback mechanism is still pending.

### Add Migration Validation
```yaml
- name: Validate migrations
  run: |
    # Check for pending migrations
    PENDING=$(docker exec munney-prod-backend php bin/console doctrine:migrations:status --no-interaction | grep "New Migrations" | awk '{print $4}')
    if [ "$PENDING" != "0" ]; then
      echo "Found $PENDING pending migrations"
      # Dry-run first
      docker exec munney-prod-backend php bin/console doctrine:migrations:migrate --dry-run --no-interaction
    fi

- name: Run migrations with backup
  run: |
    # Create pre-migration backup
    docker exec munney-prod-mysql mysqldump -u root -p${MYSQL_ROOT_PASSWORD_PROD} money_db_prod > /srv/munney-prod/backups/pre_migration_$(date +%Y%m%d_%H%M%S).sql
    # Run migrations
    docker exec munney-prod-backend php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

### Add Rollback Capability
```yaml
- name: Deploy with rollback support
  run: |
    cd /srv/munney-prod
    # Tag current images before updating
    docker tag munney-prod-backend:latest munney-prod-backend:rollback || true
    docker tag munney-prod-frontend:latest munney-prod-frontend:rollback || true

    # Deploy new version
    docker compose -f deploy/ubuntu/docker-compose.prod.yml up -d --build

- name: Rollback on failure
  if: failure()
  run: |
    cd /srv/munney-prod
    echo "Deployment failed, rolling back..."
    docker tag munney-prod-backend:rollback munney-prod-backend:latest
    docker tag munney-prod-frontend:rollback munney-prod-frontend:latest
    docker compose -f deploy/ubuntu/docker-compose.prod.yml up -d
```

## Priority 4: Replace Sleep with Health Polling ❌ PENDING

```yaml
- name: Wait for healthy services
  run: |
    echo "Waiting for services to become healthy..."
    RETRIES=30
    DELAY=5

    for i in $(seq 1 $RETRIES); do
      # Check backend
      if docker exec munney-prod-backend php bin/console about > /dev/null 2>&1; then
        # Check database
        if docker exec munney-prod-backend php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; then
          echo "Services are healthy!"
          exit 0
        fi
      fi
      echo "Attempt $i/$RETRIES - waiting ${DELAY}s..."
      sleep $DELAY
    done

    echo "Services failed to become healthy"
    exit 1
```

## Priority 5: Standardize Environment Configuration ✅ IMPLEMENTED

Environment files are configured per environment with clear structure.

### Reference Template:

```bash
# Shared variables (all environments)
OPENAI_API_KEY=
HCAPTCHA_SECRET_KEY=
HCAPTCHA_SITE_KEY=

# Database (suffix with environment)
MYSQL_ROOT_PASSWORD=
MYSQL_PASSWORD=

# JWT
JWT_PASSPHRASE=

# Email
MAILER_DSN=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=

# URLs (environment-specific)
APP_URL=
CORS_ALLOW_ORIGIN=
```

## Priority 6: Add API Health Endpoint ❌ PENDING

This would improve deployment verification.

### Recommended Implementation
Create `backend/src/Controller/HealthController.php`:

```php
#[Route('/api/health', name: 'api_health')]
public function health(EntityManagerInterface $em): JsonResponse
{
    $checks = [
        'status' => 'healthy',
        'timestamp' => (new \DateTime())->format('c'),
        'checks' => []
    ];

    // Database check
    try {
        $em->getConnection()->executeQuery('SELECT 1');
        $checks['checks']['database'] = 'ok';
    } catch (\Exception $e) {
        $checks['status'] = 'unhealthy';
        $checks['checks']['database'] = 'failed';
    }

    return new JsonResponse($checks, $checks['status'] === 'healthy' ? 200 : 503);
}
```

Update health check in workflow:
```yaml
- name: Health check
  run: |
    response=$(curl -s https://munney.munne.me/api/health)
    status=$(echo $response | jq -r '.status')
    if [ "$status" != "healthy" ]; then
      echo "Health check failed: $response"
      exit 1
    fi
    echo "Health check passed"
```

## Priority 7: Consolidate Docker Compose Files ✅ IMPLEMENTED

Docker Compose files are now properly organized:
- `docker-compose.yml` - Local development
- `deploy/ubuntu/docker-compose.dev.yml` - Server dev (devmunney)
- `deploy/ubuntu/docker-compose.prod.yml` - Server prod (munney)

### Original Recommended structure:
```
docker-compose.yml           # Base services definition
docker-compose.local.yml     # Local development overrides
docker-compose.override.yml  # (gitignored) personal overrides

deploy/
  docker-compose.dev.yml     # Server dev (standalone)
  docker-compose.prod.yml    # Server prod (standalone)
```

Remove redundant `docker-compose.prod.yml` from root.

## Implementation Status Summary

| Priority | Recommendation | Status |
|----------|---------------|--------|
| 1 | Add Testing to Pipeline | ✅ Done |
| 2 | Add PR Validation Workflow | ✅ Done |
| 3 | Improve Deployment Safety | ⚠️ Partial (backups done, rollback pending) |
| 4 | Replace Sleep with Health Polling | ❌ Pending |
| 5 | Standardize Environment Configuration | ✅ Done |
| 6 | Add API Health Endpoint | ❌ Pending |
| 7 | Consolidate Docker Compose Files | ✅ Done |

### Remaining Work
1. Implement automatic rollback on deployment failure
2. Add health endpoint for comprehensive service checks
3. Replace sleep timers with health polling
