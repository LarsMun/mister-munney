# Mister Money (Munney) - Documentation

**Last Updated:** January 20, 2026

This directory contains comprehensive documentation for the Mister Money personal finance application.

---

## Management Summary

**Mister Munney** is a production-ready personal finance application with an overall score of **8.5/10**.

### Application Status
- **Production**: Active at [munney.munne.me](https://munney.munne.me)
- **Development**: Staging at [devmunney.home.munne.me](https://devmunney.home.munne.me)
- **API Documentation**: [munney.munne.me/api/doc](https://munney.munne.me/api/doc) (Swagger UI)
- **CI/CD**: Fully automated with GitHub Actions + automatic rollback

### Key Metrics (January 2026 Assessment)
| Category | Score |
|----------|-------|
| Architecture | 9/10 |
| Security | 9/10 |
| Code Quality | 8/10 |
| Performance | 8/10 |
| Maintainability | 8/10 |
| CI/CD | 9/10 |
| Documentation | 9/10 |
| Testing | 8/10 |

### Codebase Metrics
- **Backend**: 141 PHP files, 20+ test files
- **Frontend**: 144 TypeScript files, 156+ unit tests, 5 E2E spec files
- **Documentation**: 11 files, ~4,000+ lines

### Recent Improvements (January 2026)
- ✅ Health endpoints (/api/health, /api/health/live, /api/health/ready)
- ✅ Frontend testing with Vitest (156+ unit tests)
- ✅ Component tests (ConfirmDialog, ErrorBoundary, MonthPicker)
- ✅ E2E tests with Playwright (transactions, budgets, categories, patterns, forecast)
- ✅ Backend unit tests (CategoryService, PatternService, AuditLogService)
- ✅ Automatic rollback on deployment failure
- ✅ Security Audit Log entity
- ✅ Test coverage reporting in CI
- ✅ Deployment, Security, and API documentation guides

### Remaining Nice-to-Have
1. Add monitoring/alerting (APM integration)
2. Add 2FA authentication
3. Add Redis caching

For detailed assessment, see [APPLICATION_RATING.md](APPLICATION_RATING.md).

---

## Documentation Index

| Document | Description |
|----------|-------------|
| [APPLICATION_RATING.md](APPLICATION_RATING.md) | Production readiness assessment and scores |
| [01_PROJECT_OVERVIEW.md](01_PROJECT_OVERVIEW.md) | Application architecture, tech stack, and directory structure |
| [02_CI_CD_ANALYSIS.md](02_CI_CD_ANALYSIS.md) | Current CI/CD setup (implemented) |
| [04_DATABASE_SCHEMA.md](04_DATABASE_SCHEMA.md) | Database entities, relationships, and migration history |
| [05_DOCKER_SETUP.md](05_DOCKER_SETUP.md) | Docker configuration for all environments |
| [06_TESTING_GUIDE.md](06_TESTING_GUIDE.md) | Test setup, running tests, and writing new tests |
| [07_DEVELOPMENT_WORKFLOW.md](07_DEVELOPMENT_WORKFLOW.md) | Day-to-day development workflow and common tasks |
| [08_QUICK_REFERENCE.md](08_QUICK_REFERENCE.md) | Cheat sheet for common commands and URLs |
| [09_IMPROVED_CI_CD_WORKFLOWS.md](09_IMPROVED_CI_CD_WORKFLOWS.md) | GitHub Actions workflows (now implemented) |
| [10_API_DOCUMENTATION.md](10_API_DOCUMENTATION.md) | API documentation, OpenAPI/Swagger access, and endpoints |
| [11_DEPLOYMENT_GUIDE.md](11_DEPLOYMENT_GUIDE.md) | Deployment procedures, rollback, and environment setup |
| [12_SECURITY_GUIDE.md](12_SECURITY_GUIDE.md) | Security measures, authentication, and best practices |

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

### CI/CD Status ✅
The application now has a fully working CI/CD pipeline:
- **CI Pipeline**: TypeScript checks, ESLint, PHPUnit tests, security scanning
- **Automated Deployments**: Push to develop → devmunney, push to main → production
- **Database Backups**: Daily automated backups

### Remaining Nice-to-Have
1. Add monitoring/alerting (APM integration)
2. Add 2FA authentication
3. Add Redis caching

See [02_CI_CD_ANALYSIS.md](02_CI_CD_ANALYSIS.md) for current status.

## Technology Stack

- **Backend**: Symfony 7.2 (PHP 8.3)
- **Frontend**: React 19, TypeScript, Vite, Tailwind
- **Database**: MySQL 8.0
- **Infrastructure**: Docker, Traefik, GitHub Actions

## Environments (OTAP Model)

| Stage | Environment | URL | Branch |
|-------|-------------|-----|--------|
| **O** (Ontwikkeling) | Local | localhost:3000 | feature/* |
| **T+A** (Test + Acceptatie) | devmunney | devmunney.home.munne.me | develop |
| **P** (Productie) | munney | munney.munne.me | main |

See [07_DEVELOPMENT_WORKFLOW.md](07_DEVELOPMENT_WORKFLOW.md) for the full OTAP workflow.

## Getting Help

- Check [08_QUICK_REFERENCE.md](08_QUICK_REFERENCE.md) for common commands
- Review logs: `docker compose logs -f`
- Run tests: `docker exec money-backend php bin/phpunit`

---

*Documentation last updated: January 20, 2026*
