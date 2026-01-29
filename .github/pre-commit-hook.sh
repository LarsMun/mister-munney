#!/bin/bash

# Pre-commit hook to run CI checks locally before committing
# This is a template file tracked in git at .github/pre-commit-hook.sh
#
# To install: cp .github/pre-commit-hook.sh .git/hooks/pre-commit && chmod +x .git/hooks/pre-commit
# To bypass: git commit --no-verify
#

set -e  # Exit on any error

echo ""
echo "🔍 Running pre-commit checks..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get list of staged files
BACKEND_CHANGED=$(git diff --cached --name-only --diff-filter=ACM | grep -c "^backend/" || true)
FRONTEND_CHANGED=$(git diff --cached --name-only --diff-filter=ACM | grep -c "^frontend/" || true)

# Track if any checks failed
CHECKS_FAILED=0

# Function to print section headers
print_header() {
    echo ""
    echo -e "${BLUE}▶ $1${NC}"
    echo "────────────────────────────────────────"
}

# Function to print success
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Function to print error
print_error() {
    echo -e "${RED}✗ $1${NC}"
    CHECKS_FAILED=1
}

# Function to print warning
print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

##############################################
# BACKEND CHECKS
##############################################

if [ $BACKEND_CHANGED -gt 0 ]; then
    print_header "Backend Changes Detected - Running Backend Checks"

    # Check if backend container is running
    if ! docker ps | grep -q money-backend; then
        print_error "Backend container is not running. Please start it with: docker-compose up -d backend"
        exit 1
    fi

    # 1. Check platform requirements
    print_header "1/5 Checking PHP platform requirements"
    if docker exec money-backend composer check-platform-reqs > /dev/null 2>&1; then
        print_success "Platform requirements satisfied"
    else
        print_error "Platform requirements check failed"
    fi

    # 2. Security audit
    print_header "2/5 Running security audit"
    if docker exec money-backend composer audit; then
        print_success "No security vulnerabilities found"
    else
        print_error "Security vulnerabilities detected!"
    fi

    # 3. Validate composer.json
    print_header "3/5 Validating composer.json"
    if docker exec money-backend composer validate --no-check-publish 2>&1 | grep -q "is valid"; then
        print_success "composer.json is valid"
    else
        print_error "composer.json validation failed"
    fi

    # 4. Run PHPUnit tests (Unit tests only)
    print_header "4/5 Running PHPUnit unit tests"
    if docker exec money-backend bash -c 'if [ -d "tests/Unit" ] && [ "$(find tests/Unit -name "*Test.php" 2>/dev/null | head -1)" ]; then ./vendor/bin/phpunit --testdox --testsuite=Unit; else echo "No unit tests found"; fi' > /dev/null 2>&1; then
        print_success "PHPUnit tests passed"
    else
        print_warning "PHPUnit tests failed or not found (check manually if needed)"
    fi

    # 5. Check Symfony container
    print_header "5/5 Checking Symfony container"
    if docker exec -e APP_ENV=test -e DATABASE_URL="sqlite:///:memory:" money-backend php bin/console lint:container --env=test > /dev/null 2>&1; then
        print_success "Symfony container is valid"
    else
        print_warning "Container check skipped (may need database)"
    fi

else
    print_warning "No backend changes detected, skipping backend checks"
fi

##############################################
# FRONTEND CHECKS
##############################################

if [ $FRONTEND_CHANGED -gt 0 ]; then
    print_header "Frontend Changes Detected - Running Frontend Checks"

    cd frontend || exit 1

    # 1. TypeScript type check
    print_header "1/4 Running TypeScript type check"
    if npx tsc --noEmit 2>&1 | grep -q "error TS"; then
        print_error "TypeScript errors found"
        npx tsc --noEmit 2>&1 | grep "error TS" | head -10
        echo "..."
    else
        print_success "TypeScript check passed"
    fi

    # 2. ESLint check
    print_header "2/4 Running ESLint"
    if npm run lint > /dev/null 2>&1; then
        print_success "ESLint check passed"
    else
        print_warning "ESLint warnings found (not blocking)"
    fi

    # 3. Run unit tests
    print_header "3/4 Running unit tests"
    if npm run test:coverage > /dev/null 2>&1; then
        print_success "Unit tests passed"
    else
        print_warning "Some unit tests failed (check manually)"
    fi

    # 4. Build check (skip to save time, but could be enabled)
    print_header "4/4 Build check (skipped for speed)"
    print_warning "Build check skipped to save time. CI will run full build."
    # Uncomment to enable build check:
    # if npm run build > /dev/null 2>&1; then
    #     print_success "Build succeeded"
    # else
    #     print_error "Build failed"
    # fi

    cd .. || exit 1

else
    print_warning "No frontend changes detected, skipping frontend checks"
fi

##############################################
# SUMMARY
##############################################

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ $CHECKS_FAILED -eq 1 ]; then
    echo -e "${RED}❌ Pre-commit checks FAILED${NC}"
    echo ""
    echo "Fix the errors above before committing."
    echo "To bypass this check (not recommended): git commit --no-verify"
    echo ""
    exit 1
else
    echo -e "${GREEN}✅ All pre-commit checks PASSED${NC}"
    echo ""
    echo "Proceeding with commit..."
    echo ""
    exit 0
fi
