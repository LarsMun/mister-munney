# DEPLOYMENT PIPELINE ANALYSIS
## Mister Munney Application

**Date:** November 20, 2025
**Focus:** CI/CD workflows, configuration management, deployment reliability

---

## 📊 CURRENT DEPLOYMENT ARCHITECTURE

### Environment Overview

| Environment | Location | Purpose | Docker Compose File | Auto-Deploy |
|-------------|----------|---------|---------------------|-------------|
| **Local Dev** | WSL2 (`/home/lars/dev/money`) | Development | `docker-compose.yml` | Manual |
| **Dev Server** | Ubuntu (`/srv/munney-dev`) | Testing | `deploy/ubuntu/docker-compose.dev.yml` | GitHub Actions (develop branch) |
| **Prod Server** | Ubuntu (`/srv/munney-prod`) | Production | `deploy/ubuntu/docker-compose.prod.yml` | GitHub Actions (main branch) |

### Container Naming Convention

| Environment | Backend | Frontend | Database |
|-------------|---------|----------|----------|
| **Local** | `money-backend` | `money-frontend` | `money-mysql` |
| **Dev Server** | `munney-dev-backend` | `munney-dev-frontend` | `munney-dev-mysql` |
| **Prod Server** | `munney-prod-backend` | `munney-prod-frontend` | `munney-prod-mysql` |

---

## 🔴 CRITICAL DEPLOYMENT ISSUES

### 1. MULTIPLE CONFLICTING DOCKER-COMPOSE FILES

**Severity:** 🔴 **CRITICAL** (Configuration Nightmare)

**Current Structure:**
```
/home/lars/dev/money/
├── docker-compose.yml              # Local development (HARDCODED SECRETS!)
├── docker-compose.prod.yml         # References Traefik, not used directly
└── deploy/ubuntu/
    ├── docker-compose.dev.yml      # ACTUAL dev deployment
    └── docker-compose.prod.yml     # ACTUAL prod deployment
```

**Problems:**

#### 1.1 Root `docker-compose.prod.yml` vs `deploy/ubuntu/docker-compose.prod.yml`

**Root file** (`docker-compose.prod.yml`):
- References Traefik labels
- Uses environment variables: `${OPENAI_API_KEY}`, `${JWT_SECRET_KEY}`
- Exposes port 8687 for backend
- Defines `proxy` network (external)

**Deploy file** (`deploy/ubuntu/docker-compose.prod.yml`):
- Also has Traefik labels
- **HARDCODES** `JWT_PASSPHRASE` (line 27)
- Uses different env var names: `${MYSQL_PASSWORD_PROD}`, `${OPENAI_API_KEY}`
- Defines `proxy` network (external)

**Result:**
- Two "production" configurations that don't match
- Unclear which one is actually used
- Deployment workflow uses `deploy/ubuntu/docker-compose.prod.yml`
- Root file appears to be outdated

**Solution:**
```bash
# Delete root docker-compose.prod.yml (it's not used)
rm docker-compose.prod.yml

# OR rename to docker-compose.prod.example.yml
mv docker-compose.prod.yml docker-compose.prod.example.yml

# Update README to clarify which files are used
```

#### 1.2 Environment Variable Naming Inconsistency

**Local (`docker-compose.yml`):**
- `DATABASE_URL` uses literal password
- `OPENAI_API_KEY` loaded from `backend/.env.local`
- `CORS_ALLOW_ORIGIN` uses regex pattern

**Dev (`deploy/ubuntu/docker-compose.dev.yml`):**
- `${MYSQL_PASSWORD_DEV}`
- `${OPENAI_API_KEY_DEV}`
- `CORS_ALLOW_ORIGIN` uses different regex

**Prod (`deploy/ubuntu/docker-compose.prod.yml`):**
- `${MYSQL_PASSWORD_PROD}`
- `${OPENAI_API_KEY}` (NO _PROD suffix!)
- `CORS_ALLOW_ORIGIN` uses explicit domain

**Root `.env` file:**
```bash
# From .env.example and actual server files
MYSQL_ROOT_PASSWORD=...
MYSQL_PASSWORD=...          # No suffix!
OPENAI_API_KEY=...          # No suffix!
```

**Mismatch:**
- Prod docker-compose references `${MYSQL_PASSWORD_PROD}`
- But `.env` file has `${MYSQL_PASSWORD}` and `${MYSQL_ROOT_PASSWORD}`
- Also has `${MYSQL_ROOT_PASSWORD_PROD}` and `${MYSQL_PASSWORD_PROD}` (duplicates!)

**Impact:**
- Environment variables may not be found
- Container startup failures
- Silent fallbacks to empty strings
- Hard to debug

**Recommendation:**
Standardize on ONE naming scheme:
```bash
# Option 1: Environment suffix
MYSQL_PASSWORD_DEV=...
MYSQL_PASSWORD_PROD=...
OPENAI_API_KEY_DEV=...
OPENAI_API_KEY_PROD=...

# Option 2: File-based separation (RECOMMENDED)
# .env.dev - only has dev vars
MYSQL_PASSWORD=...
OPENAI_API_KEY=...

# .env.prod - only has prod vars
MYSQL_PASSWORD=...
OPENAI_API_KEY=...
```

---

### 2. DEV DEPLOYMENT MISSING MIGRATIONS

**Severity:** 🔴 **HIGH** (Features Break on Dev)

**Evidence:**

**Production Workflow** (`.github/workflows/deploy-prod.yml`):
```yaml
- name: 🗄️ Run database migrations
  run: |
    cd /srv/munney-prod
    docker exec munney-prod-backend php bin/console doctrine:migrations:migrate --no-interaction --env=prod
    echo "✅ Migrations complete"
```

**Dev Workflow** (`.github/workflows/deploy-dev.yml`):
```yaml
- name: 🔄 Restart containers
  run: |
    cd /srv/munney-dev
    docker compose -f deploy/ubuntu/docker-compose.dev.yml down
    docker compose -f deploy/ubuntu/docker-compose.dev.yml up -d --build

- name: ✅ Deployment complete
  run: |
    echo "✅ Dev deployment successful!"
```

**Missing:**
- No migration step
- No cache clear
- No health check

**Impact:**
- New features requiring database changes fail on dev
- Dev environment falls out of sync with code
- Features appear broken in testing
- Developers waste time debugging "broken" code

**Recent Example:**
- Migration `Version20251113202926.php` adds login_attempts table
- If not applied on dev, login tracking fails silently
- CAPTCHA feature appears broken

**Migration History:**
```
Version20250922143457.php  (Sep 22, 2025)
Version20250929113841.php  (Sep 29, 2025)
Version20250929210209.php  (Sep 29, 2025)
Version20251003082439.php  (Oct 3, 2025)
Version20251014202740.php  (Oct 14, 2025)
Version20251017070454.php  (Oct 17, 2025)
Version20251017073545.php  (Oct 17, 2025)
Version20251017141039.php  (Oct 17, 2025)
Version20251019202742.php  (Oct 19, 2025)
Version20251103211742.php  (Nov 3, 2025)
Version20251103212230.php  (Nov 3, 2025)
Version20251104081409.php  (Nov 4, 2025)
Version20251104092847.php  (Nov 4, 2025)
Version20251104093517.php  (Nov 4, 2025)
Version20251105000000.php  (Nov 5, 2025)
Version20251106221057.php  (Nov 6, 2025)
Version20251107095856.php  (Nov 7, 2025)
Version20251107224624.php  (Nov 7, 2025)
Version20251113202926.php  (Nov 13, 2025) <-- Latest
```

**19 migrations total** - if dev is missing any, features break.

**Fix:**
```yaml
# Add to .github/workflows/deploy-dev.yml after restart step
- name: 🗄️ Run database migrations
  run: |
    cd /srv/munney-dev
    echo "Running migrations..."
    docker exec munney-dev-backend php bin/console doctrine:migrations:migrate --no-interaction --env=dev
    echo "✅ Migrations complete"

- name: 🧹 Clear cache
  run: |
    cd /srv/munney-dev
    docker exec munney-dev-backend php bin/console cache:clear --env=dev
    docker exec munney-dev-backend php bin/console cache:warmup --env=dev
    echo "✅ Cache refreshed"
```

---

### 3. NO PRE-DEPLOYMENT VALIDATION

**Severity:** 🟡 **HIGH** (Silent Failures)

**Current Workflow:**
1. Push to develop/main branch
2. GitHub Actions pulls code
3. Restarts containers
4. ~~Checks if it worked~~ (MISSING!)

**Missing Checks:**
- ❌ Environment variables exist before deployment
- ❌ Required secrets are set
- ❌ Docker images build successfully
- ❌ Containers start and stay running
- ❌ Database connection works
- ❌ Migrations run successfully
- ❌ Application responds to HTTP requests

**Impact:**
- Deployments succeed but app is broken
- Find out via user reports instead of CI/CD
- No rollback mechanism

**Example Failure Scenario:**
```yaml
# If HCAPTCHA_SECRET_KEY is missing:
1. Deployment "succeeds" ✅
2. Container starts ✅
3. Login works for 2 attempts ✅
4. 3rd failed attempt triggers CAPTCHA ❌
5. User cannot login ❌
6. No error in deployment logs
```

**Recommended Pre-Flight Checks:**
```yaml
- name: 🔍 Pre-deployment validation
  run: |
    cd /srv/munney-prod

    # Check required environment variables
    required_vars=(
      "MYSQL_ROOT_PASSWORD_PROD"
      "MYSQL_PASSWORD_PROD"
      "APP_SECRET_PROD"
      "OPENAI_API_KEY"
      "JWT_PASSPHRASE_PROD"
      "HCAPTCHA_SECRET_KEY"
      "MAILER_DSN"
    )

    source .env
    for var in "${required_vars[@]}"; do
      if [ -z "${!var}" ]; then
        echo "❌ ERROR: $var is not set in .env"
        exit 1
      fi
      echo "✅ $var is set"
    done

    echo "✅ All required environment variables present"

- name: 🏗️ Build and start containers
  run: |
    cd /srv/munney-prod
    docker compose -f deploy/ubuntu/docker-compose.prod.yml build --no-cache
    docker compose -f deploy/ubuntu/docker-compose.prod.yml up -d

- name: ⏳ Wait for services to be ready
  run: |
    echo "Waiting 30 seconds for services to initialize..."
    sleep 30

- name: 🏥 Health check
  run: |
    # Check backend responds
    if ! curl -f -s https://munney.munne.me/api > /dev/null; then
      echo "❌ Backend health check failed"
      docker logs munney-prod-backend --tail 50
      exit 1
    fi
    echo "✅ Backend is responding"

    # Check frontend responds
    if ! curl -f -s https://munney.munne.me/ > /dev/null; then
      echo "❌ Frontend health check failed"
      docker logs munney-prod-frontend --tail 50
      exit 1
    fi
    echo "✅ Frontend is responding"

    # Check database is accessible
    if ! docker exec munney-prod-mysql mysqladmin ping -h localhost -u root -p"$MYSQL_ROOT_PASSWORD_PROD" --silent; then
      echo "❌ Database health check failed"
      exit 1
    fi
    echo "✅ Database is responding"
```

---

### 4. NO ROLLBACK STRATEGY

**Severity:** 🟡 **HIGH** (Cannot Recover from Bad Deploy)

**Current Situation:**
- If deployment breaks production, only option is to fix forward
- No easy way to rollback to previous version
- No database backup before migrations
- Docker images are rebuilt every time (no versioning)

**Wait... Actually Found Database Backup!**

**Production workflow** has backup step:
```yaml
- name: 💾 Create database backup
  run: |
    cd /srv/munney-prod
    source .env
    BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
    echo "Creating backup: munney_prod_${BACKUP_DATE}.sql"
    if docker ps | grep -q munney-prod-mysql; then
      docker exec munney-prod-mysql mysqldump -u root -p${MYSQL_ROOT_PASSWORD_PROD} money_db_prod > /srv/munney-prod/backups/munney_prod_${BACKUP_DATE}.sql 2>/dev/null || true
      echo "✅ Backup created"
    else
      echo "⚠️  Database not running, skipping backup"
    fi
```

**Good:** Backups are created
**Problems:**
- Backups stored on same server (if server fails, backups lost)
- No retention policy (disk fills up)
- No backup verification
- No restore procedure documented
- Backup happens but no rollback mechanism uses it

**Recommended Rollback Strategy:**

#### Option 1: Git-Based Rollback
```yaml
# If deployment fails, automatically rollback
- name: 🔄 Rollback on failure
  if: failure()
  run: |
    cd /srv/munney-prod
    echo "⚠️  Deployment failed, rolling back..."

    # Checkout previous commit
    git log --oneline -5
    read -p "Enter commit hash to rollback to: " COMMIT_HASH
    git checkout $COMMIT_HASH

    # Rebuild and restart
    docker compose -f deploy/ubuntu/docker-compose.prod.yml up -d --build

    echo "✅ Rolled back to $COMMIT_HASH"
```

#### Option 2: Docker Image Versioning
```yaml
# Tag images with git commit
- name: 🏗️ Build versioned images
  run: |
    cd /srv/munney-prod
    GIT_HASH=$(git rev-parse --short HEAD)

    docker compose -f deploy/ubuntu/docker-compose.prod.yml build
    docker tag munney-prod-backend:latest munney-prod-backend:$GIT_HASH
    docker tag munney-prod-frontend:latest munney-prod-frontend:$GIT_HASH

    echo "✅ Tagged images with $GIT_HASH"

# To rollback:
# docker tag munney-prod-backend:abc123 munney-prod-backend:latest
# docker compose up -d
```

#### Option 3: Blue-Green Deployment
```yaml
# Run new version alongside old version
# Switch traffic only if new version passes health checks
# Can instantly switch back if issues occur
```

**Recommended:** Combination of Option 1 (git-based) + database backups

**Document Rollback Procedure:**
```bash
# 1. Check backup exists
ls -lh /srv/munney-prod/backups/ | tail -5

# 2. Stop containers
docker compose -f deploy/ubuntu/docker-compose.prod.yml down

# 3. Restore database
BACKUP_FILE=/srv/munney-prod/backups/munney_prod_20251120_150000.sql
docker exec -i munney-prod-mysql mysql -u root -p${MYSQL_ROOT_PASSWORD_PROD} money_db_prod < $BACKUP_FILE

# 4. Checkout previous working commit
git checkout <commit-hash>

# 5. Rebuild and restart
docker compose -f deploy/ubuntu/docker-compose.prod.yml up -d --build

# 6. Verify
curl https://munney.munne.me/api
```

---

## 🟡 MEDIUM PRIORITY ISSUES

### 5. HARDCODED JWT_PASSPHRASE IN PROD COMPOSE FILE

**Location:** `deploy/ubuntu/docker-compose.prod.yml:27`
```yaml
JWT_PASSPHRASE: '***REMOVED***'
```

**Issue:**
- Should use `${JWT_PASSPHRASE_PROD}` environment variable
- Hardcoding defeats purpose of environment variables
- Same passphrase is in `.env` file anyway

**Why It's Here:**
- Appears to be override or mistake
- `.env` file has `JWT_PASSPHRASE` without suffix
- Compose file references it directly instead of from .env

**Fix:**
```yaml
# deploy/ubuntu/docker-compose.prod.yml
environment:
  JWT_PASSPHRASE: ${JWT_PASSPHRASE_PROD}

# /srv/munney-prod/.env
JWT_PASSPHRASE_PROD=<new secure passphrase>
```

---

### 6. NO AUTOMATED TESTS IN DEPLOYMENT PIPELINE

**Severity:** 🟡 **MEDIUM**

**Current CI/CD:**
```yaml
on:
  push:
    branches:
      - main

jobs:
  deploy:
    runs-on: self-hosted
    steps:
      - Pull code
      - Build images
      - Restart containers
      - Done ✅
```

**Missing:**
- ❌ Run PHPUnit tests before deployment
- ❌ Run frontend tests
- ❌ Run integration tests
- ❌ Lint checks
- ❌ Type checks

**Impact:**
- Broken code reaches production
- No early warning of regressions
- Manual testing required

**Recommended:**
```yaml
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: |
          cd backend
          composer install --no-interaction

      - name: Run PHPUnit tests
        run: |
          cd backend
          php bin/phpunit

      - name: Run frontend tests
        run: |
          cd frontend
          npm install
          npm test

  deploy:
    needs: test  # Only deploy if tests pass
    runs-on: self-hosted
    steps:
      # ... existing deployment steps
```

---

## 🟢 LOW PRIORITY IMPROVEMENTS

### 7. MULTIPLE .env.backup FILES ON SERVER

**Location:** `/srv/munney-prod/`

**Evidence:**
```
-rw-rw-r-- 1 lars lars  534 Nov 13 14:41 .env.backup
-rw-rw-r-- 1 lars lars  846 Nov 20 16:11 .env.backup.20251120_161108
```

**Issue:**
- Manual backups accumulate
- May contain old secrets
- Not version controlled
- No cleanup policy

**Recommendation:**
```bash
# Remove manual backups
rm /srv/munney-prod/.env.backup*

# Use git for tracking changes instead
cd /srv/munney-prod
git diff .env  # See what changed

# Or use automated backup with retention
# E.g., keep only last 7 days of backups
```

---

### 8. DEPLOYMENT DOESN'T VERIFY FINAL STATE

**Current:**
```yaml
- name: ✅ Deployment complete
  run: |
    echo "✅ Production deployment successful!"
    docker ps --filter 'label=project=munney'
```

**Issue:**
- Just shows containers are running
- Doesn't verify they're actually working
- No check if migrations succeeded

**Better:**
```yaml
- name: ✅ Verify deployment success
  run: |
    # Check all containers are running
    if [ $(docker ps --filter 'label=project=munney' --filter 'label=environment=production' | wc -l) -lt 4 ]; then
      echo "❌ Not all containers are running"
      exit 1
    fi

    # Check backend responds
    if ! curl -f https://munney.munne.me/api/accounts > /dev/null 2>&1; then
      echo "❌ Backend API not responding"
      exit 1
    fi

    # Check database migrations status
    PENDING=$(docker exec munney-prod-backend php bin/console doctrine:migrations:status --no-interaction | grep "New Migrations" | awk '{print $NF}')
    if [ "$PENDING" != "0" ]; then
      echo "❌ Pending migrations: $PENDING"
      exit 1
    fi

    echo "✅ All checks passed!"
    echo "🌐 URL: https://munney.munne.me"
    echo "🗄️ Migrations: Up to date"
    echo "📊 Containers: All running"
```

---

## 📋 DEPLOYMENT CHECKLIST

### Pre-Deployment Checklist
- [ ] All tests pass locally
- [ ] Database migrations tested locally
- [ ] Environment variables documented
- [ ] Secrets rotated if needed
- [ ] Backup of production database exists
- [ ] Deployment window scheduled (low traffic)
- [ ] Rollback plan documented
- [ ] Team notified

### Deployment Steps
1. [ ] Create database backup
2. [ ] Pull latest code
3. [ ] Verify environment variables
4. [ ] Build Docker images
5. [ ] Run migrations (in transaction if possible)
6. [ ] Restart containers
7. [ ] Clear cache
8. [ ] Run health checks
9. [ ] Verify critical user flows
10. [ ] Monitor logs for errors

### Post-Deployment Checklist
- [ ] Frontend loads correctly
- [ ] Login works
- [ ] API endpoints respond
- [ ] Database queries work
- [ ] No errors in logs
- [ ] CAPTCHA works (test with failed logins)
- [ ] Email sending works
- [ ] File uploads work

### Rollback Procedure
If deployment fails:
1. [ ] Stop containers
2. [ ] Restore database from backup
3. [ ] Checkout previous working commit
4. [ ] Rebuild images
5. [ ] Restart containers
6. [ ] Verify rollback successful
7. [ ] Investigate failure
8. [ ] Document lessons learned

---

## 🎯 RECOMMENDED IMPROVEMENTS

### Priority 1 (This Week)
1. Add migrations to dev deployment workflow
2. Add missing HCAPTCHA_SECRET_KEY to servers
3. Remove hardcoded JWT_PASSPHRASE from compose file
4. Add pre-deployment environment variable checks
5. Document rollback procedure

### Priority 2 (This Month)
1. Add automated tests to CI/CD pipeline
2. Implement proper secret management
3. Add deployment health checks
4. Create deployment runbook
5. Set up monitoring and alerting

### Priority 3 (Next Quarter)
1. Implement blue-green deployment
2. Add canary deployments for production
3. Set up automated rollbacks on failure
4. Implement database migration safety checks
5. Add performance regression testing

---

**Document Status:** ✅ COMPLETE
**Critical Actions:** 5 immediate fixes required
**Overall Assessment:** Deployment pipeline works but lacks safety measures
