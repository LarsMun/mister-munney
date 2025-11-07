# Cleanup Tasks - Mister Munney

**Date:** November 6, 2025
**Focus:** File organization, unused code, documentation cleanup

---

## 📊 Current Project Organization

### Root Directory Status: ⚠️ **CLUTTERED**

Currently **38 files** in root directory, including:
- 20+ `.md` documentation files
- 5 `.json` API spec files
- 3 `.yml` docker-compose files
- Misc CSV/SQL/shell scripts

**Issue:** Hard to navigate, unclear organization

---

## 🗂️ ROOT DIRECTORY REORGANIZATION

### Priority: 🟡 MEDIUM | Effort: XS | Impact: LOW (but improves maintainability)

### Current Root Structure
```
/project-root/
├── ACCESSIBILITY.md
├── BACKEND_CODE_REVIEW.md
├── BACKEND_REVIEW_SUMMARY.md
├── CLAUDE.md
├── CODE_REVIEW_INDEX.md
├── CONTAINER_OVERVIEW.md
├── DATABASE_OPTIMIZATION_SCRIPTS.sql
├── DATABASE_PERFORMANCE_REPORT.md
├── DATABASE_PERFORMANCE_SUMMARY.txt
├── DEPLOY_QUICK_REF.md
├── IMPLEMENTATION_PLAN.md
├── MIGRATIE_INSTRUCTIES.md
├── ONTBREKENDE_CATEGORIEEN.md
├── PLAN_CATEGORIEBEHEER.md
├── PRODUCTION_DEPLOYMENT_CHECKLIST.md
├── README.md
├── accounts-spec.json
├── backend_analysis_report.md
├── categories-spec.json
├── composer.json
├── composer.lock
├── complete_tree.txt
├── create_test_files.sh
├── docker-compose.prod.yml
├── docker-compose.yml
├── munney_adaptive_dashboard_dev_spec.md
├── openapi.json
├── package-lock.json
├── package.json
├── patterns-spec.json
├── paypal.CSV
├── phase1_detailed_implementation.md
├── qodana.yaml
├── savings-accounts-spec.json
├── transactions-spec.json
├── backend/
├── frontend/
└── claude_improvements/  (NEW - this directory)
```

---

### Proposed Root Structure

```
/project-root/
├── README.md                          # Keep - main entry point
├── CLAUDE.md                          # Keep - Claude Code instructions
├── docker-compose.yml                 # Move to docker/
├── docker-compose.prod.yml            # Move to docker/
├── qodana.yaml                        # Keep - IDE config
│
├── backend/                           # Existing
├── frontend/                          # Existing
│
├── docs/                              # NEW - All documentation
│   ├── planning/                      # Planning & specs
│   │   ├── IMPLEMENTATION_PLAN.md
│   │   ├── PLAN_CATEGORIEBEHEER.md
│   │   ├── MIGRATIE_INSTRUCTIES.md
│   │   ├── ONTBREKENDE_CATEGORIEEN.md
│   │   ├── phase1_detailed_implementation.md
│   │   ├── munney_adaptive_dashboard_dev_spec.md
│   │   └── ACCESSIBILITY.md
│   │
│   ├── api/                           # API specifications
│   │   ├── accounts-spec.json
│   │   ├── categories-spec.json
│   │   ├── patterns-spec.json
│   │   ├── savings-accounts-spec.json
│   │   ├── transactions-spec.json
│   │   └── openapi.json
│   │
│   ├── deployment/                    # Deployment docs
│   │   ├── CONTAINER_OVERVIEW.md
│   │   ├── DEPLOY_QUICK_REF.md
│   │   └── PRODUCTION_DEPLOYMENT_CHECKLIST.md
│   │
│   ├── architecture/                  # Architecture docs
│   │   ├── decisions/                 # ADRs (new)
│   │   └── diagrams/                  # Architecture diagrams (new)
│   │
│   └── reviews/                       # Code reviews & audits
│       ├── BACKEND_CODE_REVIEW.md
│       ├── BACKEND_REVIEW_SUMMARY.md
│       ├── CODE_REVIEW_INDEX.md
│       ├── DATABASE_PERFORMANCE_REPORT.md
│       ├── DATABASE_PERFORMANCE_SUMMARY.txt
│       └── backend_analysis_report.md
│
├── docker/                            # NEW - Docker configs
│   ├── docker-compose.yml             # Dev
│   ├── docker-compose.prod.yml        # Prod
│   ├── .env.example                   # Example env vars
│   └── README.md                      # Docker setup instructions
│
├── scripts/                           # NEW - Utility scripts
│   ├── create_test_files.sh
│   ├── DATABASE_OPTIMIZATION_SCRIPTS.sql
│   └── complete_tree.txt
│
└── temp/                              # NEW - Temporary files (gitignored)
    └── paypal.CSV
```

---

### Migration Script

```bash
#!/bin/bash
# migrate_files.sh - Run from project root

echo "📁 Creating new directory structure..."
mkdir -p docs/planning
mkdir -p docs/api
mkdir -p docs/deployment
mkdir -p docs/architecture/decisions
mkdir -p docs/architecture/diagrams
mkdir -p docs/reviews
mkdir -p docker
mkdir -p scripts
mkdir -p temp

echo "📦 Moving planning documents..."
mv IMPLEMENTATION_PLAN.md docs/planning/
mv PLAN_CATEGORIEBEHEER.md docs/planning/
mv MIGRATIE_INSTRUCTIES.md docs/planning/
mv ONTBREKENDE_CATEGORIEEN.md docs/planning/
mv phase1_detailed_implementation.md docs/planning/
mv munney_adaptive_dashboard_dev_spec.md docs/planning/
mv ACCESSIBILITY.md docs/planning/

echo "📦 Moving API specifications..."
mv accounts-spec.json docs/api/
mv categories-spec.json docs/api/
mv patterns-spec.json docs/api/
mv savings-accounts-spec.json docs/api/
mv transactions-spec.json docs/api/
mv openapi.json docs/api/

echo "📦 Moving deployment documentation..."
mv CONTAINER_OVERVIEW.md docs/deployment/
mv DEPLOY_QUICK_REF.md docs/deployment/
mv PRODUCTION_DEPLOYMENT_CHECKLIST.md docs/deployment/

echo "📦 Moving code review documents..."
mv BACKEND_CODE_REVIEW.md docs/reviews/
mv BACKEND_REVIEW_SUMMARY.md docs/reviews/
mv CODE_REVIEW_INDEX.md docs/reviews/
mv DATABASE_PERFORMANCE_REPORT.md docs/reviews/
mv DATABASE_PERFORMANCE_SUMMARY.txt docs/reviews/
mv backend_analysis_report.md docs/reviews/

echo "📦 Moving Docker configurations..."
mv docker-compose.yml docker/
mv docker-compose.prod.yml docker/
mv .env.example docker/

echo "📦 Moving scripts..."
mv create_test_files.sh scripts/
mv DATABASE_OPTIMIZATION_SCRIPTS.sql scripts/
mv complete_tree.txt scripts/

echo "📦 Moving temporary files..."
mv paypal.CSV temp/  # Should be in .gitignore

echo "✅ Migration complete!"
echo ""
echo "📝 Don't forget to:"
echo "  1. Update docker commands to reference new paths"
echo "  2. Update .gitignore to ignore temp/"
echo "  3. Update README.md with new structure"
echo "  4. Commit changes"
```

---

### Update .gitignore

```gitignore
# Add to .gitignore

# Temporary files
/temp/
*.CSV
*:Zone.Identifier

# Local environment
.env.local
.env.*.local

# IDE
.idea/
.vscode/

# Build artifacts
/vendor/
/node_modules/
/var/cache/
/var/log/

# Sensitive
/config/jwt/*.pem
/secrets/
```

---

### Update Docker Commands

After moving docker-compose files:

```bash
# Old commands
docker compose up -d

# New commands (from project root)
docker compose -f docker/docker-compose.yml up -d
docker compose -f docker/docker-compose.prod.yml up -d

# OR: Create symlinks for convenience
ln -s docker/docker-compose.yml ./docker-compose.yml
```

---

## 🗑️ REMOVE UNUSED CODE

### Priority: 🟢 LOW | Effort: XS | Impact: LOW

### 1. Commented Out Code

**backend/config/packages/security.yaml:**
```yaml
# Remove all commented access_control examples
access_control:
    # - { path: ^/admin, roles: ROLE_ADMIN }  # DELETE
    # - { path: ^/profile, roles: ROLE_USER } # DELETE
```

**Action:** Remove after implementing authentication

---

### 2. Deprecated Doctrine Annotations

**Issue:** Using deprecated `doctrine/annotations` package

**Action:** Replace with PHP 8 attributes

```bash
# Check usage
grep -r "use Doctrine\\Common\\Annotations" backend/src/

# Replace with attributes
# @ORM\Entity → #[ORM\Entity]
# @ORM\Table → #[ORM\Table]
```

**Effort:** 2-3 hours to update all files

---

### 3. Unused Imports

**Run PHPStan to detect:**
```bash
docker exec money-backend vendor/bin/phpstan analyze src --level=1
```

**Common issues:**
- Imported but unused exceptions
- Imported but unused services
- Imported but unused DTOs

---

### 4. Dead Code Detection

**Install PHP Dead Code Detector:**
```bash
composer require --dev sebastian/phpdcd
vendor/bin/phpdcd backend/src/
```

**Expected findings:**
- Unused private methods
- Unused service methods
- Unused mapper methods

---

## 📚 DOCUMENTATION IMPROVEMENTS

### Priority: 🟢 MEDIUM | Effort: M | Impact: MEDIUM

### 1. Create docs/README.md

```markdown
# Mister Munney Documentation

## 📂 Directory Structure

### Planning & Specifications
- `/planning/` - Project plans, implementation specs, migration guides
- `/api/` - OpenAPI/JSON specs for API endpoints

### Deployment
- `/deployment/` - Container setup, deployment checklists, production guides

### Architecture
- `/architecture/decisions/` - Architecture Decision Records (ADRs)
- `/architecture/diagrams/` - System architecture diagrams

### Reviews & Audits
- `/reviews/` - Code reviews, performance reports, security audits

## 🚀 Quick Links

- [Main README](../README.md)
- [API Documentation](http://localhost:8787/api/doc)
- [Deployment Checklist](deployment/PRODUCTION_DEPLOYMENT_CHECKLIST.md)
- [Container Overview](deployment/CONTAINER_OVERVIEW.md)
```

---

### 2. Create Architecture Decision Records

**Template:** `docs/architecture/decisions/TEMPLATE.md`

```markdown
# [NUMBER]. [TITLE]

Date: YYYY-MM-DD

## Status
[Proposed | Accepted | Deprecated | Superseded]

## Context
What is the issue or problem that we're addressing?

## Decision
What is the change that we're proposing or have agreed to implement?

## Consequences
What becomes easier or more difficult because of this change?

### Positive
- Benefit 1
- Benefit 2

### Negative
- Trade-off 1
- Trade-off 2

## Alternatives Considered
What other options did we consider?
```

---

**Example ADRs to create:**

1. `0001-use-symfony-framework.md`
2. `0002-use-money-php-library.md`
3. `0003-domain-driven-design.md`
4. `0004-feature-flags-in-database.md`
5. `0005-remove-transaction-type-on-categories.md` (already exists in CLAUDE.md)

---

### 3. Add Code Documentation

**Missing PHPDoc in:**
- `BudgetInsightsService.php` - Algorithm explanation
- `ProjectAggregatorService.php` - Aggregation logic
- `TransactionRepository.php` - Complex queries
- `PatternService.php` - Pattern matching

**Action:** Add docblocks to complex methods (see 02_code_quality_report.md section 6)

---

## 🧹 DEPENDENCY CLEANUP

### Priority: 🟢 LOW | Effort: S | Impact: LOW

### 1. Remove Abandoned Packages

**Backend:**
```bash
# doctrine/annotations is ABANDONED
composer remove doctrine/annotations

# Already using PHP 8 attributes ✅
```

---

### 2. Remove Unused Dependencies

**Check unused:**
```bash
docker exec money-backend composer why-not
docker exec money-frontend npm ls --all
```

**Potential candidates:**
- Development tools in production deps
- Unused UI libraries

---

### 3. Update Outdated Dependencies

**See 01_executive_summary.md for full list**

**Critical updates:**
- `doctrine/dbal` 3.10 → 4.3
- `moneyphp/money` 3.3 → 4.8
- `phpunit/phpunit` 10.5 → 12.4
- `tailwindcss` 3.4 → 4.1

---

## 🎨 CODE STYLE CONSISTENCY

### Priority: 🔵 LOW | Effort: S | Impact: LOW

### 1. Add PHP-CS-Fixer

```bash
composer require --dev friendsofphp/php-cs-fixer
```

```php
// .php-cs-fixer.php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/backend/src')
    ->in(__DIR__ . '/backend/tests');

$config = new PhpCsFixer\Config();
return $config
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder);
```

**Run:**
```bash
docker exec money-backend vendor/bin/php-cs-fixer fix --dry-run
docker exec money-backend vendor/bin/php-cs-fixer fix  # Apply fixes
```

---

### 2. Add ESLint/Prettier for Frontend

**Already configured!** ✅

**Run:**
```bash
docker exec money-frontend npm run lint
docker exec money-frontend npm run lint:fix
```

---

## ✅ CLEANUP CHECKLIST

### File Organization
- [ ] Create new directory structure (docs/, docker/, scripts/, temp/)
- [ ] Run migration script
- [ ] Update .gitignore
- [ ] Update docker-compose references
- [ ] Update README.md with new structure
- [ ] Commit changes

### Code Cleanup
- [ ] Remove commented code in security.yaml
- [ ] Remove unused imports (PHPStan)
- [ ] Run dead code detector
- [ ] Update deprecated annotations to attributes
- [ ] Remove abandoned doctrine/annotations package

### Documentation
- [ ] Create docs/README.md
- [ ] Create ADR template
- [ ] Write 5 initial ADRs
- [ ] Add PHPDoc to complex methods
- [ ] Update main README with new structure

### Dependencies
- [ ] Remove doctrine/annotations
- [ ] Check for unused dependencies
- [ ] Update outdated dependencies (see executive summary)
- [ ] Run composer audit
- [ ] Run npm audit

### Code Style
- [ ] Install PHP-CS-Fixer
- [ ] Configure .php-cs-fixer.php
- [ ] Run php-cs-fixer
- [ ] Run ESLint on frontend
- [ ] Fix linting issues

---

## 📊 Cleanup Impact

### Before Cleanup:
- 📁 38 files in root directory
- 🗑️ Commented code throughout
- 📦 Abandoned packages in use
- 📚 Scattered documentation
- ⚠️ Inconsistent code style

### After Cleanup:
- 📁 5 files in root directory (80% cleaner)
- ✅ No commented code
- ✅ No abandoned packages
- ✅ Organized documentation
- ✅ Consistent code style (PSR-12)

### Benefits:
- **Easier navigation** - Clear structure
- **Faster onboarding** - New developers find docs easily
- **Better maintainability** - Consistent code style
- **Reduced technical debt** - No unused code

---

## ⏱️ Effort Estimation

### Quick Tasks (4 hours)
- File reorganization: 2 hours
- Remove commented code: 1 hour
- Update .gitignore: 0.5 hour
- Create docs/README: 0.5 hour

### Medium Tasks (8 hours)
- Remove unused imports: 2 hours
- Update annotations to attributes: 3 hours
- Add missing PHPDoc: 2 hours
- Create initial ADRs: 1 hour

### Low Priority (4 hours)
- Setup PHP-CS-Fixer: 1 hour
- Run and fix code style: 2 hours
- Dead code detection: 1 hour

**Total Effort: 16 hours** (2 developer days)

---

**Document Location:** `./claude_improvements/06_cleanup_tasks.md`
**Last Updated:** November 6, 2025
**Status:** ✅ Ready for Review
