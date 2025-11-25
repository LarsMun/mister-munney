# Mister Money (Munney) - Documentation

This directory contains comprehensive documentation for the Mister Money personal finance application.

## Documentation Index

| Document | Description |
|----------|-------------|
| [01_PROJECT_OVERVIEW.md](01_PROJECT_OVERVIEW.md) | Application architecture, tech stack, and directory structure |
| [02_CI_CD_ANALYSIS.md](02_CI_CD_ANALYSIS.md) | Current CI/CD setup analysis with identified issues |
| [03_CI_CD_RECOMMENDATIONS.md](03_CI_CD_RECOMMENDATIONS.md) | Prioritized recommendations to fix CI/CD issues |
| [04_DATABASE_SCHEMA.md](04_DATABASE_SCHEMA.md) | Database entities, relationships, and migration history |
| [05_DOCKER_SETUP.md](05_DOCKER_SETUP.md) | Docker configuration for all environments |
| [06_TESTING_GUIDE.md](06_TESTING_GUIDE.md) | Test setup, running tests, and writing new tests |
| [07_DEVELOPMENT_WORKFLOW.md](07_DEVELOPMENT_WORKFLOW.md) | Day-to-day development workflow and common tasks |
| [08_QUICK_REFERENCE.md](08_QUICK_REFERENCE.md) | Cheat sheet for common commands and URLs |
| [09_IMPROVED_CI_CD_WORKFLOWS.md](09_IMPROVED_CI_CD_WORKFLOWS.md) | Ready-to-use improved GitHub Actions workflows |

## Quick Start

### First Time Setup
```bash
# Clone repository
git clone <repo-url>
cd money

# Copy environment file
cp .env.local.example .env.local
# Edit .env.local with your values

# Start containers
docker compose up -d

# Access the application
# Frontend: http://localhost:3000
# Backend API: http://localhost:8787/api
```

### Daily Development
```bash
# Start environment
docker compose up -d

# Create feature branch
git checkout develop
git pull origin develop
git checkout -b feature/my-feature

# Make changes, then commit
git add -p
git commit -m "feat: description"

# Push and create PR
git push -u origin feature/my-feature
```

## Key Highlights

### CI/CD Issues Found
1. **No pre-deployment testing** - Tests don't run before deployment
2. **No frontend build validation** - TypeScript errors not caught
3. **No rollback mechanism** - Manual intervention on failure
4. **Arbitrary sleep timers** - Should use health polling

### Priority Fixes
1. Add PR validation workflow with tests
2. Add backend tests to deployment pipeline
3. Implement health check polling
4. Add automatic rollback on failure

See [03_CI_CD_RECOMMENDATIONS.md](03_CI_CD_RECOMMENDATIONS.md) for full details.

## Technology Stack

- **Backend**: Symfony 7.2 (PHP 8.3)
- **Frontend**: React 19, TypeScript, Vite, Tailwind
- **Database**: MySQL 8.0
- **Infrastructure**: Docker, Traefik, GitHub Actions

## Environments

| Environment | URL | Branch |
|-------------|-----|--------|
| Local | localhost:3000 | any |
| Development | devmunney.home.munne.me | develop |
| Production | munney.munne.me | main |

## Getting Help

- Check [08_QUICK_REFERENCE.md](08_QUICK_REFERENCE.md) for common commands
- Review logs: `docker compose logs -f`
- Run tests: `docker exec money-backend php bin/phpunit`

---

*Documentation generated: 2025-11-24*
