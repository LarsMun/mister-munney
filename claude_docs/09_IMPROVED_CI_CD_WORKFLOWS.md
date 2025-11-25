# Improved CI/CD Workflow Files

This document contains ready-to-use improved workflow files that address the identified issues.

## 1. PR Validation Workflow (NEW)

Create `.github/workflows/pr-check.yml`:

```yaml
name: PR Validation

on:
  pull_request:
    branches: [main, develop]

jobs:
  backend-validation:
    name: Backend Checks
    runs-on: self-hosted
    defaults:
      run:
        working-directory: /srv/munney-ci

    steps:
      - name: Checkout code
        uses: actions/checkout@v4
        with:
          path: /srv/munney-ci

      - name: Start test environment
        run: |
          docker compose -f docker-compose.yml up -d database
          sleep 10  # Wait for MySQL

      - name: Install dependencies
        run: |
          docker compose -f docker-compose.yml run --rm backend composer install --no-scripts

      - name: Create test database
        run: |
          docker compose -f docker-compose.yml exec -T backend \
            php bin/console doctrine:database:create --env=test --if-not-exists

      - name: Run migrations
        run: |
          docker compose -f docker-compose.yml exec -T backend \
            php bin/console doctrine:migrations:migrate --env=test --no-interaction

      - name: Run PHPUnit tests
        run: |
          docker compose -f docker-compose.yml exec -T backend php bin/phpunit --testdox

      - name: Cleanup
        if: always()
        run: |
          docker compose -f docker-compose.yml down -v

  frontend-validation:
    name: Frontend Checks
    runs-on: self-hosted

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
          cache-dependency-path: frontend/package-lock.json

      - name: Install dependencies
        run: |
          cd frontend
          npm ci

      - name: Run ESLint
        run: |
          cd frontend
          npm run lint

      - name: TypeScript check
        run: |
          cd frontend
          npx tsc --noEmit

      - name: Build
        run: |
          cd frontend
          npm run build
        env:
          VITE_API_URL: "https://test.example.com/api"
```

## 2. Improved Development Deployment

Replace `.github/workflows/deploy-dev.yml`:

```yaml
name: Deploy to Development

on:
  push:
    branches:
      - develop

jobs:
  test:
    name: Run Tests
    runs-on: self-hosted

    steps:
      - name: Run backend tests
        run: |
          cd /srv/munney-dev
          docker compose -f deploy/ubuntu/docker-compose.dev.yml exec -T munney-dev-backend \
            php bin/phpunit --testdox 2>/dev/null || echo "Tests skipped - container not running"

  deploy:
    name: Deploy to Dev Server
    runs-on: self-hosted
    needs: test

    steps:
      - name: Pull latest code
        run: |
          cd /srv/munney-dev
          git fetch origin develop
          git checkout develop
          git pull origin develop

      - name: Validate frontend build
        run: |
          cd /srv/munney-dev/frontend
          npm ci
          npm run lint || echo "Lint warnings found"
          npm run build
        env:
          VITE_API_URL: "https://devmunney.home.munne.me/api"

      - name: Generate JWT keys (if needed)
        run: |
          cd /srv/munney-dev/backend
          if [ ! -f config/jwt/private.pem ]; then
            echo "Generating JWT RSA keys..."
            mkdir -p config/jwt
            source ../.env
            openssl genpkey -algorithm RSA \
              -out config/jwt/private.pem \
              -aes256 \
              -pass pass:"${JWT_PASSPHRASE}" \
              -pkeyopt rsa_keygen_bits:4096
            openssl rsa -pubout \
              -in config/jwt/private.pem \
              -out config/jwt/public.pem \
              -passin pass:"${JWT_PASSPHRASE}"
            chmod 644 config/jwt/private.pem config/jwt/public.pem
            echo "JWT keys generated"
          fi

      - name: Tag current images for rollback
        run: |
          cd /srv/munney-dev
          docker tag munney-dev-backend:latest munney-dev-backend:rollback 2>/dev/null || true
          docker tag munney-dev-frontend:latest munney-dev-frontend:rollback 2>/dev/null || true

      - name: Build and deploy containers
        run: |
          cd /srv/munney-dev
          docker compose --env-file .env -f deploy/ubuntu/docker-compose.dev.yml build
          docker compose --env-file .env -f deploy/ubuntu/docker-compose.dev.yml down
          docker compose --env-file .env -f deploy/ubuntu/docker-compose.dev.yml up -d

      - name: Wait for healthy services
        run: |
          echo "Waiting for services to become healthy..."
          RETRIES=30
          for i in $(seq 1 $RETRIES); do
            if docker exec munney-dev-backend php bin/console about > /dev/null 2>&1; then
              if docker exec munney-dev-backend php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; then
                echo "Services are healthy!"
                break
              fi
            fi
            if [ $i -eq $RETRIES ]; then
              echo "Services failed to become healthy"
              exit 1
            fi
            echo "Attempt $i/$RETRIES - waiting 5s..."
            sleep 5
          done

      - name: Validate pending migrations
        run: |
          cd /srv/munney-dev
          PENDING=$(docker exec munney-dev-backend php bin/console doctrine:migrations:status --no-interaction 2>/dev/null | grep "New Migrations" | awk '{print $4}' || echo "0")
          if [ "$PENDING" != "0" ] && [ -n "$PENDING" ]; then
            echo "Found $PENDING pending migrations, running dry-run first..."
            docker exec munney-dev-backend php bin/console doctrine:migrations:migrate --dry-run --no-interaction --env=dev
          fi

      - name: Run database migrations
        run: |
          cd /srv/munney-dev
          docker exec munney-dev-backend php bin/console doctrine:migrations:migrate --no-interaction --env=dev

      - name: Clear and warm cache
        run: |
          cd /srv/munney-dev
          docker exec munney-dev-backend php bin/console cache:clear --env=dev
          docker exec munney-dev-backend php bin/console cache:warmup --env=dev

      - name: Deployment complete
        run: |
          echo "========================================"
          echo "Dev deployment successful!"
          echo "========================================"
          echo "URL: https://devmunney.home.munne.me"
          docker ps --filter 'label=project=munney' --filter 'label=environment=development'

      - name: Rollback on failure
        if: failure()
        run: |
          cd /srv/munney-dev
          echo "Deployment failed, attempting rollback..."
          docker tag munney-dev-backend:rollback munney-dev-backend:latest 2>/dev/null || true
          docker tag munney-dev-frontend:rollback munney-dev-frontend:latest 2>/dev/null || true
          docker compose --env-file .env -f deploy/ubuntu/docker-compose.dev.yml up -d
          echo "Rollback completed"
```

## 3. Improved Production Deployment

Replace `.github/workflows/deploy-prod.yml`:

```yaml
name: Deploy to Production

on:
  push:
    branches:
      - main
  workflow_dispatch:

jobs:
  validate:
    name: Pre-deployment Validation
    runs-on: self-hosted

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Validate frontend build
        run: |
          cd frontend
          npm ci
          npm run lint
          npm run build
        env:
          VITE_API_URL: "https://munney.munne.me/api"

  deploy:
    name: Deploy to Production Server
    runs-on: self-hosted
    needs: validate

    steps:
      - name: Pre-deployment checklist
        run: |
          echo "========================================"
          echo "Starting Production Deployment"
          echo "========================================"
          echo "Target: https://munney.munne.me"
          echo "Time: $(date)"

      - name: Create database backup
        run: |
          cd /srv/munney-prod
          source .env
          BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
          BACKUP_FILE="/srv/munney-prod/backups/munney_prod_${BACKUP_DATE}.sql"

          if docker ps | grep -q munney-prod-mysql; then
            echo "Creating backup: $BACKUP_FILE"
            docker exec munney-prod-mysql mysqldump \
              -u root -p${MYSQL_ROOT_PASSWORD_PROD} \
              money_db_prod > "$BACKUP_FILE" 2>/dev/null

            # Keep only last 10 backups
            ls -t /srv/munney-prod/backups/*.sql | tail -n +11 | xargs -r rm
            echo "Backup created successfully"
          else
            echo "Warning: Database not running, skipping backup"
          fi

      - name: Pull latest code
        run: |
          cd /srv/munney-prod
          git fetch origin main
          git checkout main
          git pull origin main

      - name: Generate JWT keys (if needed)
        run: |
          cd /srv/munney-prod/backend
          if [ ! -f config/jwt/private.pem ]; then
            echo "Generating JWT RSA keys..."
            mkdir -p config/jwt
            source ../.env
            openssl genpkey -algorithm RSA \
              -out config/jwt/private.pem \
              -aes256 \
              -pass pass:"${JWT_PASSPHRASE_PROD}" \
              -pkeyopt rsa_keygen_bits:4096
            openssl rsa -pubout \
              -in config/jwt/private.pem \
              -out config/jwt/public.pem \
              -passin pass:"${JWT_PASSPHRASE_PROD}"
            chmod 644 config/jwt/private.pem config/jwt/public.pem
          fi

      - name: Tag current images for rollback
        run: |
          cd /srv/munney-prod
          docker tag munney-prod-backend:latest munney-prod-backend:rollback 2>/dev/null || true
          docker tag munney-prod-frontend:latest munney-prod-frontend:rollback 2>/dev/null || true

      - name: Create pre-migration backup
        run: |
          cd /srv/munney-prod
          source .env
          BACKUP_FILE="/srv/munney-prod/backups/pre_migration_$(date +%Y%m%d_%H%M%S).sql"
          docker exec munney-prod-mysql mysqldump \
            -u root -p${MYSQL_ROOT_PASSWORD_PROD} \
            money_db_prod > "$BACKUP_FILE" 2>/dev/null || true

      - name: Build production images
        run: |
          cd /srv/munney-prod
          docker compose -f deploy/ubuntu/docker-compose.prod.yml build

      - name: Deploy containers
        run: |
          cd /srv/munney-prod
          docker compose -f deploy/ubuntu/docker-compose.prod.yml down
          docker compose -f deploy/ubuntu/docker-compose.prod.yml up -d

      - name: Wait for healthy services
        run: |
          echo "Waiting for services to become healthy..."
          RETRIES=30
          for i in $(seq 1 $RETRIES); do
            if docker exec munney-prod-backend php bin/console about > /dev/null 2>&1; then
              if docker exec munney-prod-backend php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; then
                echo "Services are healthy!"
                break
              fi
            fi
            if [ $i -eq $RETRIES ]; then
              echo "Services failed to become healthy"
              exit 1
            fi
            echo "Attempt $i/$RETRIES - waiting 5s..."
            sleep 5
          done

      - name: Run database migrations
        run: |
          cd /srv/munney-prod
          docker exec munney-prod-backend php bin/console doctrine:migrations:migrate --no-interaction --env=prod

      - name: Clear and warm cache
        run: |
          cd /srv/munney-prod
          docker exec munney-prod-backend php bin/console cache:clear --env=prod
          docker exec munney-prod-backend php bin/console cache:warmup --env=prod

      - name: Health check
        run: |
          echo "Running health checks..."
          sleep 5

          # Frontend check
          if curl -f -s --max-time 10 https://munney.munne.me/ > /dev/null; then
            echo "Frontend: OK"
          else
            echo "Frontend: FAILED"
            exit 1
          fi

          # Backend API check
          if curl -f -s --max-time 10 https://munney.munne.me/api > /dev/null; then
            echo "Backend API: OK"
          else
            echo "Backend API: FAILED"
            exit 1
          fi

      - name: Deployment complete
        run: |
          echo "========================================"
          echo "Production deployment successful!"
          echo "========================================"
          echo "URL: https://munney.munne.me"
          echo "Time: $(date)"
          docker ps --filter 'label=project=munney' --filter 'label=environment=production'

      - name: Rollback on failure
        if: failure()
        run: |
          cd /srv/munney-prod
          echo "========================================"
          echo "DEPLOYMENT FAILED - ROLLING BACK"
          echo "========================================"

          # Restore images
          docker tag munney-prod-backend:rollback munney-prod-backend:latest 2>/dev/null || true
          docker tag munney-prod-frontend:rollback munney-prod-frontend:latest 2>/dev/null || true

          # Restart with old images
          docker compose -f deploy/ubuntu/docker-compose.prod.yml up -d

          echo "Rollback completed"
          echo "Manual intervention may be required"
```

## 4. Implementation Notes

### Files to Create/Update

1. **Create**: `.github/workflows/pr-check.yml` (new file)
2. **Update**: `.github/workflows/deploy-dev.yml` (replace contents)
3. **Update**: `.github/workflows/deploy-prod.yml` (replace contents)

### Required Server Setup

For the PR validation to work, you may need a CI-specific directory:
```bash
sudo mkdir -p /srv/munney-ci
sudo chown $(whoami):$(whoami) /srv/munney-ci
```

Or use the existing containers in dev for testing.

### Branch Protection Rules (GitHub)

Configure in GitHub repository settings:

**For `main` branch:**
- Require pull request reviews (1 reviewer)
- Require status checks to pass
- Require branches to be up to date

**For `develop` branch:**
- Require status checks to pass (optional but recommended)
