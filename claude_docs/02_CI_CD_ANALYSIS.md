# CI/CD Pipeline Analysis

**Last Updated:** January 19, 2026

## Current Setup

### GitHub Actions Workflows

The project uses **three** GitHub Actions workflows:

#### 1. CI Pipeline (`ci.yml`) ✅ NEW
- **Trigger**: Push/PR to `develop` or `main` branches
- **Backend Checks**:
  - PHP 8.3 setup with required extensions
  - Composer dependency caching
  - Security vulnerability scanning (`composer audit`)
  - PHPUnit unit tests
  - Symfony container validation
- **Frontend Checks**:
  - Node.js 20 with npm caching
  - TypeScript type checking (`tsc --noEmit`)
  - ESLint validation
  - Production build validation

#### 2. Development Pipeline (`deploy-dev.yml`)
- **Trigger**: Push to `develop` branch
- **Steps**:
  1. Pull latest code
  2. Generate JWT keys (if missing)
  3. Restart containers with rebuild
  4. Wait 20 seconds
  5. Run migrations
  6. Clear/warm cache

#### 3. Production Pipeline (`deploy-prod.yml`)
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

## Issues Status

### Resolved Issues ✅

#### 1. Pre-deployment Testing - FIXED
CI pipeline now runs PHPUnit tests, TypeScript checks, and ESLint before code can be merged.

#### 2. Build Validation for Frontend - FIXED
CI pipeline validates frontend build with `npm run build` before deployment.

#### 3. Linting/Static Analysis - FIXED
ESLint and TypeScript type checking are now part of CI.

### Remaining Issues

#### 1. No Rollback Mechanism
**Problem**: No automated rollback on failure.
**Impact**: Manual intervention required when deployment fails.
**Priority**: Medium

#### 2. Database Migration Safety
**Problem**: Migrations run directly without validation.
**Impact**: Failed migrations can leave database in inconsistent state.
**Priority**: Low (rare occurrence)

#### 3. Hardcoded Sleep Timers
```yaml
sleep 20  # Arbitrary wait time
```
**Better approach**: Use health check polling.
**Priority**: Low (works in practice)

### Configuration Notes

#### Docker-compose Files
```
./docker-compose.yml                     # Local development
./deploy/ubuntu/docker-compose.dev.yml   # Server dev (devmunney)
./deploy/ubuntu/docker-compose.prod.yml  # Server prod (munney)
```
Each environment has its dedicated configuration.

## Git Workflow Analysis

### Current Flow
```
Feature Branch → develop (auto-deploy to devmunney)
                    ↓
               PR to main → main (auto-deploy to munney)
```

### Workflow Status

1. **Combined T+A environment** - devmunney serves as both Test and Acceptance before production ✅
2. **PR checks** - CI pipeline runs on all PRs with tests and build validation ✅
3. **Branch strategy** - Feature branches merge to develop, develop merges to main ✅

## Docker Configuration Analysis

### Development (Local)
- Uses volume mounts for live reload
- Frontend runs Vite dev server
- Full debug enabled

### Development (Server - devmunney)
- Traefik-integrated
- Volume mounts for source code
- Automatic deployment on push to develop

### Production (Server - munney)
- Multi-stage build for frontend (optimized nginx)
- No source volume mounts (immutable containers)
- HSTS and security headers via Traefik
- Database backup before deployment

## Health Check

Production deployment includes basic health check:
```yaml
if curl -f -s https://munney.munne.me/ > /dev/null; then
  echo "Frontend responding"
fi
```

Future improvement: Add comprehensive health endpoint that checks database connectivity.
