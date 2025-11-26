# CI/CD Pipeline Analysis

## Current Setup

### GitHub Actions Workflows

The project uses two GitHub Actions workflows running on a **self-hosted runner**:

#### 1. Development Pipeline (`deploy-dev.yml`)
- **Trigger**: Push to `develop` branch
- **Steps**:
  1. Pull latest code
  2. Generate JWT keys (if missing)
  3. Restart containers with rebuild
  4. Wait 20 seconds
  5. Run migrations
  6. Clear/warm cache

#### 2. Production Pipeline (`deploy-prod.yml`)
- **Trigger**: Push to `main` branch + manual dispatch
- **Steps**:
  1. Create database backup
  2. Pull latest code
  3. Generate JWT keys (if missing)
  4. Build with `--no-cache`
  5. Deploy containers
  6. Wait 20 seconds
  7. Run migrations
  8. Clear/warm cache
  9. Health check (frontend + backend)

## Identified Issues

### Critical Issues

#### 1. No Pre-deployment Testing
**Problem**: Neither pipeline runs tests before deployment.
```yaml
# Missing step:
- name: Run backend tests
  run: docker exec munney-backend php bin/phpunit
```
**Impact**: Broken code can be deployed to development/production.

#### 2. No Build Validation for Frontend
**Problem**: Frontend build is not validated before deployment.
```yaml
# Missing step:
- name: Build frontend
  run: docker exec munney-frontend npm run build
```
**Impact**: TypeScript errors or build failures only discovered during deployment.

#### 3. No Database Migration Safety
**Problem**: Migrations run directly without validation.
```yaml
# Current:
docker exec munney-dev-backend php bin/console doctrine:migrations:migrate --no-interaction
```
**Impact**: Failed migrations can leave database in inconsistent state.

#### 4. No Rollback Mechanism
**Problem**: No automated rollback on failure.
**Impact**: Manual intervention required when deployment fails.

### Medium Priority Issues

#### 5. Hardcoded Sleep Timers
```yaml
sleep 20  # Arbitrary wait time
```
**Better approach**: Use health check polling.

#### 6. Environment Variable Inconsistency
- Local: `MYSQL_PASSWORD`
- Dev: `MYSQL_PASSWORD_DEV`
- Prod: `MYSQL_PASSWORD_PROD`

Different naming conventions cause confusion.

#### 7. No Linting/Static Analysis
Missing PHP CS Fixer, PHPStan, ESLint in CI.

#### 8. Docker Image Not Cached
Production builds use `--no-cache` every time, slowing deployments.

### Configuration Issues

#### 9. Multiple docker-compose Files
```
./docker-compose.yml           # Local dev
./docker-compose.prod.yml      # Root level (legacy?)
./deploy/ubuntu/docker-compose.dev.yml   # Server dev
./deploy/ubuntu/docker-compose.prod.yml  # Server prod
```
This creates confusion about which config is authoritative.

#### 10. Port Conflicts
Both dev and prod use port 3333 for MySQL external access:
```yaml
# Dev: 3334:3306
# Prod: 3333:3306
```
Potential conflict if both run on same host.

## Git Workflow Analysis

### Current Flow
```
Feature Branch → develop (auto-deploy to dev)
                    ↓
               PR to main → main (auto-deploy to prod)
```

### Issues with Current Flow

1. **No staging environment** - Changes go directly from dev to prod
2. **No PR checks** - PRs don't run automated tests
3. **No code review enforcement** - Branch protection may not be configured

## Docker Configuration Analysis

### Development (Local)
- Uses volume mounts for live reload
- Frontend runs Vite dev server
- Full debug enabled

### Development (Server)
- Similar to local but Traefik-integrated
- Different env var names (`MYSQL_PASSWORD_DEV`)
- Volume mounts for source code

### Production (Server)
- Multi-stage build for frontend (optimized nginx)
- No source volume mounts (immutable containers)
- Read-only filesystem where possible
- HSTS and security headers via Traefik

## Health Check Limitations

Current health check is basic:
```yaml
if curl -f -s https://munney.munne.me/ > /dev/null; then
  echo "Frontend responding"
fi
```

Problems:
- Only checks HTTP 200, not actual functionality
- No backend API endpoint validation
- No database connectivity check
