# Development Workflow Guide

## Git Branching Strategy

### Current Setup
```
main (production)
  └── develop (development server)
        └── feature/* (local development)
```

### Branch Protection (Recommended)
- `main`: Require PR, require status checks, require review
- `develop`: Require status checks

## Daily Development Workflow

### 1. Start Your Environment

```bash
# Navigate to project
cd /home/lars/dev/money

# Ensure you're on develop
git checkout develop
git pull origin develop

# Start containers
docker compose up -d

# Check status
docker compose ps
```

### 2. Create Feature Branch

```bash
# Create and switch to feature branch
git checkout -b feature/your-feature-name

# Or for bug fixes
git checkout -b fix/issue-description
```

### 3. Development Cycle

```bash
# Backend changes
# - Edit files in backend/src/
# - Changes auto-reload (volume mount)

# Frontend changes
# - Edit files in frontend/src/
# - Vite hot-reloads automatically

# Database changes
docker exec money-backend php bin/console make:migration
docker exec money-backend php bin/console doctrine:migrations:migrate
```

### 4. Testing Changes

```bash
# Run backend tests
docker exec money-backend php bin/phpunit

# Check backend logs
docker compose logs -f backend

# Check frontend console
# Open browser dev tools

# Test API directly
curl http://localhost:8787/api/accounts
```

### 5. Commit Changes

```bash
# Stage changes
git add -p  # Review each change

# Commit with conventional message
git commit -m "feat: add budget history feature"

# Types: feat, fix, docs, style, refactor, test, chore
```

### 6. Push and Create PR

```bash
# Push feature branch
git push -u origin feature/your-feature-name

# Create PR on GitHub
# - Base: develop
# - Title: Same as commit message
# - Description: What/why/testing done
```

### 7. After PR Merge to Develop

The CI/CD pipeline automatically:
1. Deploys to development server
2. Runs migrations
3. Clears cache

Verify at: https://devmunney.home.munne.me

### 8. Production Release

```bash
# Create PR from develop to main
# - Review changes since last release
# - Test on development server

# After merge, CI/CD automatically:
# 1. Creates database backup
# 2. Deploys to production
# 3. Runs health check

# Verify at: https://munney.munne.me
```

## Common Tasks

### Adding a New API Endpoint

1. **Create Controller** (if new domain):
```php
// backend/src/YourDomain/Controller/YourController.php
#[Route('/api/your-resource')]
class YourController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        // Implementation
    }
}
```

2. **Register in services.yaml** (usually auto-wired)

3. **Add to CORS if needed** (check `config/packages/nelmio_cors.yaml`)

### Adding a Database Migration

```bash
# After modifying an Entity
docker exec money-backend php bin/console make:migration

# Review the generated migration
cat backend/migrations/Version*.php

# Run migration locally
docker exec money-backend php bin/console doctrine:migrations:migrate

# Commit the migration file
git add backend/migrations/
git commit -m "feat: add new_column to table"
```

### Adding a Frontend Page

1. **Create component**:
```typescript
// frontend/src/domains/yourFeature/pages/YourPage.tsx
export function YourPage() {
  return <div>Your content</div>;
}
```

2. **Add route** in `App.tsx`:
```typescript
<Route path="/your-feature" element={<YourPage />} />
```

3. **Add navigation** if needed

### Debugging

#### Backend Debugging
```bash
# View real-time logs
docker compose logs -f backend

# Check Symfony profiler
# Visit: http://localhost:8787/_profiler

# Debug query
docker exec money-backend php bin/console doctrine:query:sql "SELECT * FROM account"
```

#### Frontend Debugging
```bash
# View container logs
docker compose logs -f frontend

# Browser dev tools:
# - React DevTools extension
# - Network tab for API calls
# - Console for errors
```

#### Database Debugging
```bash
# Connect to MySQL
docker exec -it money-mysql mysql -u money -p money_db

# Or use external tool on port 3333
```

## Environment Variables

### Local Development
Copy and edit `.env.local`:
```bash
cp .env.local.example .env.local
# Edit with your values
```

### Adding New Variables

1. Add to `.env.local.example` (template)
2. Add to `docker-compose.yml` (environment section)
3. Add to backend config if needed
4. Update `.env.dev.example` and `.env.prod.example`
5. Document in this guide

## Troubleshooting

### Container Won't Start
```bash
# Check logs
docker compose logs backend

# Rebuild
docker compose build --no-cache backend
docker compose up -d
```

### Permission Issues
```bash
# Fix backend permissions
docker exec money-backend chown -R www-data:www-data var/
```

### Database Connection Failed
```bash
# Check if MySQL is ready
docker exec money-mysql mysqladmin ping -u root -p

# Check DATABASE_URL in container
docker exec money-backend env | grep DATABASE
```

### Frontend Not Hot-Reloading
```bash
# Check if Vite is running
docker compose logs frontend

# Restart with fresh node_modules
docker compose down frontend
docker compose build --no-cache frontend
docker compose up -d frontend
```

### Cache Issues
```bash
# Clear Symfony cache
docker exec money-backend php bin/console cache:clear

# Clear frontend cache
docker exec money-frontend rm -rf node_modules/.vite
docker compose restart frontend
```

## Pre-Commit Checklist

Before creating a PR:

- [ ] Tests pass: `docker exec money-backend php bin/phpunit`
- [ ] No lint errors: `cd frontend && npm run lint`
- [ ] Migration works: tested locally
- [ ] No console errors in browser
- [ ] Commit messages follow convention
- [ ] PR description explains changes
