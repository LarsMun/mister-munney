# Action Plan - Mister Munney Improvements

**Date:** November 6, 2025
**Total Timeline:** 10 weeks (5 sprints × 2 weeks)
**Total Effort:** 152 developer hours (~19 days)

---

## 🎯 Overall Strategy

### Priorities
1. **Security First** - Fix critical vulnerabilities (REQUIRED for production)
2. **Test Coverage** - Eliminate gaps in critical domains
3. **Code Quality** - Reduce technical debt
4. **Performance** - Optimize for scale
5. **Polish** - Cleanup and documentation

### Team Structure
- **Sprint 1-2:** 2 developers (security + testing)
- **Sprint 3:** 2 developers (refactoring)
- **Sprint 4:** 1 developer (performance)
- **Sprint 5:** 1 developer (cleanup)

---

## 📅 SPRINT 1: Security Foundation (Week 1-2)

**Goal:** Implement authentication & authorization (CRITICAL for production)

**Team:** 2 developers
**Duration:** 2 weeks (80 hours total, 40 hours each)
**Priority:** 🔴 CRITICAL

### Sprint Tasks

#### Task 1.1: Database Setup (Day 1)
**Assignee:** Developer 1
**Effort:** 4 hours

- [ ] Create User entity with proper fields
- [ ] Create UserAccount junction table (many-to-many)
- [ ] Generate and review migration
- [ ] Run migration on development database
- [ ] Test user creation manually

**Acceptance Criteria:**
- User table exists with email, password, roles
- UserAccount relationship properly configured
- Migration runs without errors

---

#### Task 1.2: JWT Configuration (Day 1-2)
**Assignee:** Developer 1
**Effort:** 6 hours

- [ ] Install `lexik/jwt-authentication-bundle`
- [ ] Generate JWT keypair
- [ ] Configure lexik_jwt_authentication.yaml
- [ ] Configure security.yaml with JWT firewall
- [ ] Test JWT generation manually

**Deliverables:**
- JWT keypair generated (not in git!)
- Configuration files updated
- JWT tokens can be generated

---

#### Task 1.3: Authentication Endpoints (Day 2-3)
**Assignee:** Developer 1
**Effort:** 10 hours

- [ ] Create AuthController with register endpoint
- [ ] Implement password hashing (Argon2id)
- [ ] Implement login endpoint (handled by lexik)
- [ ] Implement refresh token endpoint
- [ ] Add validation on registration
- [ ] Write OpenAPI docs for auth endpoints
- [ ] Test with Postman/Insomnia

**Endpoints:**
```
POST /api/register
  Body: { "email": "user@example.com", "password": "secure123" }
  Response: { "user": { "id": 1, "email": "..." } }

POST /api/login
  Body: { "email": "...", "password": "..." }
  Response: { "token": "eyJ...", "refresh_token": "..." }

POST /api/refresh
  Body: { "refresh_token": "..." }
  Response: { "token": "eyJ..." }
```

**Acceptance Criteria:**
- Users can register with email/password
- Login returns JWT token
- Token refresh works
- All endpoints documented in Swagger

---

#### Task 1.4: Access Control Implementation (Day 3-5)
**Assignee:** Developer 2
**Effort:** 16 hours

- [ ] Create AccountVoter for ownership checks
- [ ] Create BudgetVoter for budget access
- [ ] Create CategoryVoter for category access
- [ ] Update all controllers to check ownership
- [ ] Add `@IsGranted` or `denyAccessUnlessGranted` checks

**Files to update:**
- AccountController.php
- BudgetController.php
- BudgetVersionController.php
- CategoryController.php
- TransactionController.php
- PatternController.php
- SavingsAccountController.php
- ExternalPaymentController.php
- And 9 more controllers...

**Example:**
```php
#[Route('/api/accounts/{id}', methods: ['GET'])]
public function getAccount(int $id): JsonResponse
{
    $account = $this->findOrFail($this->accountRepository, $id, 'Account');

    // SECURITY CHECK
    $this->denyAccessUnlessGranted('view', $account);

    return $this->json($this->accountMapper->toDto($account));
}
```

**Acceptance Criteria:**
- All 17 controllers have ownership checks
- Users can only access their own data
- Proper 403 errors returned for unauthorized access

---

#### Task 1.5: CORS Restriction (Day 5)
**Assignee:** Developer 2
**Effort:** 2 hours

- [ ] Update nelmio_cors.yaml with specific origins
- [ ] Add FRONTEND_URL environment variable
- [ ] Test CORS from frontend
- [ ] Verify browser blocks other origins

**Acceptance Criteria:**
- CORS only allows frontend domain
- Other origins get CORS error
- Credentials (cookies/auth) allowed

---

#### Task 1.6: Rate Limiting (Day 6)
**Assignee:** Developer 2
**Effort:** 6 hours

- [ ] Install symfony/rate-limiter
- [ ] Configure rate limiter (login: 5/15min, API: 1000/hour)
- [ ] Apply to AuthController (login endpoint)
- [ ] Apply globally to all API endpoints
- [ ] Test rate limiting behavior
- [ ] Add X-RateLimit headers

**Acceptance Criteria:**
- Login limited to 5 attempts per 15 minutes
- API limited to 1000 requests per hour
- Rate limit headers present in responses
- 429 Too Many Requests returned when exceeded

---

#### Task 1.7: Security Headers (Day 6)
**Assignee:** Developer 2
**Effort:** 2 hours

- [ ] Install nelmio/security-bundle
- [ ] Configure security headers (CSP, X-Frame-Options, etc.)
- [ ] Test headers in browser devtools
- [ ] Run security scanner (securityheaders.com)

**Acceptance Criteria:**
- All security headers present
- A+ rating on securityheaders.com
- CSP configured properly

---

#### Task 1.8: Credentials Protection (Day 7)
**Assignee:** Developer 1
**Effort:** 3 hours

- [ ] Move database password to Docker secrets
- [ ] Move JWT keys to secure location
- [ ] Update docker-compose.prod.yml
- [ ] Document secret management
- [ ] Test on staging environment

**Acceptance Criteria:**
- No passwords in plain text files
- Secrets properly managed
- Production configuration secure

---

#### Task 1.9: Frontend Integration (Day 7-8)
**Assignee:** Developer 1
**Effort:** 10 hours

- [ ] Create login page component
- [ ] Create registration page component
- [ ] Implement JWT token storage (localStorage)
- [ ] Add Authorization header to all API calls
- [ ] Implement token refresh logic
- [ ] Handle 401 Unauthorized (redirect to login)
- [ ] Test full authentication flow

**Acceptance Criteria:**
- Users can log in via frontend
- JWT token stored and sent with requests
- Token refresh automatic
- Logout works properly

---

#### Task 1.10: Testing & Documentation (Day 9-10)
**Assignee:** Both developers
**Effort:** 16 hours

- [ ] Write integration tests for authentication
- [ ] Write tests for authorization (voters)
- [ ] Test all endpoints with/without auth
- [ ] Document authentication flow in README
- [ ] Create user onboarding documentation
- [ ] Security testing (try to bypass auth)

**Acceptance Criteria:**
- 20+ tests covering authentication
- All tests passing
- Documentation complete
- No security bypasses found

---

### Sprint 1 Deliverables

✅ **Security:**
- JWT authentication working
- User registration and login
- Account ownership verification on ALL endpoints
- CORS restricted to frontend
- Rate limiting on login and API
- Security headers configured
- Credentials secured

✅ **Documentation:**
- Authentication flow documented
- API docs updated with auth requirements
- User onboarding guide

✅ **Testing:**
- 20+ integration tests for auth
- Manual security testing passed

---

## 📅 SPRINT 2: Test Coverage (Week 3-4)

**Goal:** Fill critical gaps in test coverage

**Team:** 1 developer
**Duration:** 2 weeks (32 hours total)
**Priority:** 🔴 HIGH

### Sprint Tasks

#### Task 2.1: Pattern Domain Tests (Day 1-3)
**Effort:** 12 hours

- [ ] Write tests for PatternService (CRUD operations)
- [ ] Write tests for PatternMatchingService (string matching)
- [ ] Write tests for PatternAssignService (bulk operations)
- [ ] Test edge cases (empty patterns, special characters)
- [ ] Achieve 90%+ coverage on Pattern domain

**Test scenarios:**
- Create pattern with valid data
- Update pattern
- Delete pattern (verify unlinks from transactions)
- Match transaction description against patterns
- Bulk assign patterns to transactions
- Handle special regex characters

**Target:** 15 tests

---

#### Task 2.2: AI Services Tests (Day 3-5)
**Effort:** 10 hours

- [ ] Mock OpenAI API responses
- [ ] Write tests for AiPatternDiscoveryService
- [ ] Write tests for AiCategorizationService
- [ ] Test error handling (API failures, rate limits)
- [ ] Test cost calculation logic

**Test scenarios:**
- AI pattern discovery with mock responses
- AI categorization suggestions
- Handle OpenAI API errors gracefully
- Respect token limits
- Cost calculation accuracy

**Target:** 10 tests

---

#### Task 2.3: Budget API Integration Tests (Day 5-7)
**Effort:** 10 hours

- [ ] Test GET /api/budgets/active endpoint
- [ ] Test GET /api/budgets/older endpoint
- [ ] Test POST /api/budgets (project creation)
- [ ] Test POST /api/budgets/{id}/external-payments
- [ ] Test GET /api/budgets/{id}/details (aggregation)
- [ ] Test file upload for external payments
- [ ] Test edge cases (empty projects, invalid dates)

**Test scenarios:**
- Active budgets returned correctly
- Older budgets excluded
- Project totals aggregated properly
- External payment creation
- File upload validation
- Time series calculation

**Target:** 12 tests

---

### Sprint 2 Deliverables

✅ **Test Coverage:**
- Pattern domain: 0% → 90%+ (15 tests)
- AI Services: 0% → 85%+ (10 tests)
- Budget API: 0% → 80%+ (12 tests)

✅ **Overall Coverage:**
- From 65% → 85%+

---

## 📅 SPRINT 3: Code Quality (Week 5-6)

**Goal:** Refactor code quality issues

**Team:** 2 developers
**Duration:** 2 weeks (40 hours total, 20 hours each)
**Priority:** 🟡 HIGH

### Sprint Tasks

#### Task 3.1: Create Reusable Traits (Day 1)
**Assignee:** Developer 1
**Effort:** 4 hours

- [ ] Create EntityLookupTrait
- [ ] Create ValidationTrait
- [ ] Write tests for traits
- [ ] Document trait usage

**Deliverables:**
- `backend/src/Shared/Trait/EntityLookupTrait.php`
- `backend/src/Shared/Trait/ValidationTrait.php`
- Documentation in PHPDoc

---

#### Task 3.2: Refactor Services with Traits (Day 1-3)
**Assignee:** Developer 1
**Effort:** 12 hours

- [ ] Update BudgetService (11 replacements)
- [ ] Update TransactionService (9 replacements)
- [ ] Update PatternService (8 replacements)
- [ ] Update CategoryService (4 replacements)
- [ ] Update 14+ controllers (validation)
- [ ] Test after each refactoring
- [ ] Verify all tests still pass

**Impact:**
- Removes ~360 lines of duplicated code
- 73 entity lookups → reusable trait
- 14 validation handlers → reusable trait

---

#### Task 3.3: Split Large Files - Backend (Day 3-6)
**Assignee:** Developer 2
**Effort:** 16 hours

**Files to split:**

1. **TransactionRepository.php** (935 → 400 lines)
   - Extract TransactionStatisticsRepository
   - Extract TransactionQueryBuilder

2. **BudgetController.php** (614 → 200 lines)
   - Create BudgetCategoryController
   - Create BudgetSummaryController

3. **TransactionService.php** (555 → 300 lines)
   - Extract TransactionStatisticsService

4. **CategoryController.php** (527 → 200 lines)
   - Create CategoryMergeController
   - Create CategoryStatisticsController

**Process for each:**
- [ ] Create new files
- [ ] Move methods to new files
- [ ] Update routing
- [ ] Update tests
- [ ] Verify functionality

---

#### Task 3.4: Split Large Files - Frontend (Day 6-8)
**Assignee:** Developer 1
**Effort:** 12 hours

**Files to split:**

1. **ProjectDetailPage.tsx** (751 → 200 lines)
   - Extract ProjectOverviewTab
   - Extract ProjectEntriesTab
   - Extract ProjectFilesTab

2. **PatternDiscovery.tsx** (539 → 200 lines)
   - Extract useAiPatternSuggestions hook
   - Extract PatternSuggestionList component

**Process:**
- [ ] Create new component files
- [ ] Move JSX and logic
- [ ] Update imports
- [ ] Test UI functionality

---

#### Task 3.5: Add Service Interfaces (Day 8-10)
**Assignee:** Developer 2
**Effort:** 8 hours

- [ ] Create BudgetServiceInterface
- [ ] Create TransactionServiceInterface
- [ ] Create CategoryServiceInterface
- [ ] Create PatternServiceInterface
- [ ] Update services to implement interfaces
- [ ] Update dependency injection config
- [ ] Update controllers to use interfaces
- [ ] Verify tests still pass

**Impact:**
- Loose coupling
- Easier to mock
- Better testability

---

### Sprint 3 Deliverables

✅ **Code Reduction:**
- 360 lines of duplicated code removed
- 7 large files split into 19 focused files

✅ **Architecture:**
- Service interfaces for 4 major services
- Reusable traits for common patterns

✅ **Maintainability:**
- +40% improvement in maintainability
- Easier to navigate codebase

---

## 📅 SPRINT 4: Performance (Week 7-8)

**Goal:** Optimize application performance

**Team:** 1 developer
**Duration:** 2 weeks (24 hours total)
**Priority:** 🟢 MEDIUM

### Sprint Tasks

#### Task 4.1: Redis Setup (Day 1)
**Effort:** 4 hours

- [ ] Add Redis to docker-compose.yml
- [ ] Install Symfony cache adapters
- [ ] Configure cache pools
- [ ] Test Redis connection

---

#### Task 4.2: Implement Caching (Day 1-3)
**Effort:** 8 hours

- [ ] Cache budget summaries (1 hour TTL)
- [ ] Cache category statistics (24 hour TTL)
- [ ] Cache feature flags (5 minute TTL)
- [ ] Implement cache invalidation on updates
- [ ] Test cache behavior

**Expected Impact:**
- 60-80% faster dashboard
- 90% faster budget summaries

---

#### Task 4.3: Add Database Indexes (Day 3)
**Effort:** 3 hours

- [ ] Create migration for new indexes
- [ ] Add idx_transaction_date
- [ ] Add idx_transaction_category_date
- [ ] Add idx_transaction_account_date_category
- [ ] Add idx_pattern_account_category
- [ ] Run migration
- [ ] Verify query performance

**Expected Impact:**
- 50-70% faster date range queries
- 70-90% faster budget calculations

---

#### Task 4.4: Fix N+1 Queries (Day 4-5)
**Effort:** 6 hours

- [ ] Add eager loading to TransactionRepository
- [ ] Add eager loading to BudgetRepository
- [ ] Add eager loading to PatternRepository
- [ ] Test query counts (before/after)
- [ ] Verify no performance regression

**Expected Impact:**
- 90% fewer queries
- 60-80% faster endpoints

---

#### Task 4.5: Frontend Optimization (Day 5-6)
**Effort:** 3 hours

- [ ] Implement code splitting (lazy routes)
- [ ] Add React.memo to 5 key components
- [ ] Add debouncing to search inputs
- [ ] Test bundle size reduction

**Expected Impact:**
- 60% smaller initial bundle
- 90% fewer search API calls

---

### Sprint 4 Deliverables

✅ **Performance Gains:**
- Dashboard: 1.5s → 300ms (75% faster)
- Budget summary: 500ms → 50ms (90% faster)
- Search: 90% fewer API calls

✅ **Infrastructure:**
- Redis caching layer
- 4 new database indexes

---

## 📅 SPRINT 5: Polish & Cleanup (Week 9-10)

**Goal:** Cleanup, documentation, and final touches

**Team:** 1 developer
**Duration:** 2 weeks (16 hours total)
**Priority:** 🔵 LOW

### Sprint Tasks

#### Task 5.1: File Reorganization (Day 1)
**Effort:** 3 hours

- [ ] Run migration script (06_cleanup_tasks.md)
- [ ] Update .gitignore
- [ ] Update docker-compose references
- [ ] Test Docker commands still work
- [ ] Commit changes

---

#### Task 5.2: Dependency Updates (Day 2-3)
**Effort:** 6 hours

- [ ] Update Composer dependencies
- [ ] Update npm dependencies
- [ ] Run tests after updates
- [ ] Fix any breaking changes
- [ ] Document migration notes

---

#### Task 5.3: Code Style & Cleanup (Day 3-4)
**Effort:** 4 hours

- [ ] Install PHP-CS-Fixer
- [ ] Run php-cs-fixer
- [ ] Run ESLint
- [ ] Remove commented code
- [ ] Remove unused imports

---

#### Task 5.4: Documentation (Day 4-5)
**Effort:** 3 hours

- [ ] Create docs/README.md
- [ ] Write 5 initial ADRs
- [ ] Add missing PHPDoc
- [ ] Update main README
- [ ] Final review of all reports

---

### Sprint 5 Deliverables

✅ **Organization:**
- Clean root directory
- Organized documentation

✅ **Dependencies:**
- All packages up to date
- No security vulnerabilities

✅ **Documentation:**
- Complete documentation structure
- 5 Architecture Decision Records

---

## 📊 Overall Impact Summary

### Development Metrics

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| Security Score | 2/10 | 9/10 | +350% |
| Test Coverage | 65% | 85%+ | +20% |
| Code Duplication | 360 lines | 0 lines | -100% |
| Large Files (>500) | 7 files | 0 files | -100% |
| Dashboard Load Time | 1.5s | 300ms | -75% |
| API Response Time | 500ms | 50ms | -90% |

### Business Impact

**Before Implementation:**
- ⛔ NOT production ready (security)
- ⚠️ Scaling concerns (performance)
- ⚠️ High maintenance burden (code quality)

**After Implementation:**
- ✅ Production ready
- ✅ Can handle 10x users
- ✅ 40% faster maintenance

**ROI:**
- Investment: 152 developer hours (~19 days)
- Savings: 30% reduction in maintenance time
- Break-even: ~3 months

---

## 📋 Implementation Checklist

### Pre-Implementation
- [ ] Review all 7 reports with team
- [ ] Prioritize based on business needs
- [ ] Assign developers to sprints
- [ ] Set up project management board (Jira/Trello)
- [ ] Schedule sprint planning meetings

### During Implementation
- [ ] Daily standups (15 min)
- [ ] Weekly demo to stakeholders
- [ ] Sprint retrospectives
- [ ] Code reviews for all PRs
- [ ] Continuous testing

### Post-Implementation
- [ ] Security audit (external)
- [ ] Performance benchmarking
- [ ] User acceptance testing
- [ ] Production deployment
- [ ] Monitor for issues

---

## 🎯 Success Criteria

### Sprint 1: Security ✅
- [ ] Users can register and login
- [ ] All endpoints require authentication
- [ ] Ownership verification working
- [ ] Security tests passing
- [ ] No unauthorized access possible

### Sprint 2: Testing ✅
- [ ] Pattern domain 90%+ coverage
- [ ] AI services 85%+ coverage
- [ ] Budget API 80%+ coverage
- [ ] Overall coverage 85%+
- [ ] All tests passing

### Sprint 3: Code Quality ✅
- [ ] 360 lines duplication removed
- [ ] 7 large files split
- [ ] Service interfaces implemented
- [ ] All tests still passing
- [ ] Code review approved

### Sprint 4: Performance ✅
- [ ] Redis caching working
- [ ] Dashboard loads in <500ms
- [ ] Budget summary in <100ms
- [ ] Database indexes added
- [ ] N+1 queries eliminated

### Sprint 5: Polish ✅
- [ ] Files organized
- [ ] Dependencies updated
- [ ] Code style consistent
- [ ] Documentation complete
- [ ] Final QA passed

---

## 🚀 Deployment Plan

### Staging Deployment (After Sprint 1)
1. Deploy authentication changes
2. Test thoroughly
3. Fix any issues
4. Get stakeholder approval

### Production Deployment (After Sprint 5)
1. Schedule maintenance window
2. Backup database
3. Deploy all changes
4. Run migrations
5. Smoke test critical flows
6. Monitor for 24 hours
7. Rollback plan ready

---

## 📞 Next Steps

1. **Week 0 (Now):**
   - [ ] Review all reports
   - [ ] Schedule kick-off meeting
   - [ ] Assign developers
   - [ ] Set up development environment

2. **Week 1 (Sprint 1 Start):**
   - [ ] Sprint planning
   - [ ] Start security implementation
   - [ ] Daily standups begin

3. **Week 10 (Sprint 5 End):**
   - [ ] Final QA
   - [ ] Production deployment
   - [ ] Post-deployment monitoring
   - [ ] Celebrate! 🎉

---

**Document Location:** `./claude_improvements/07_action_plan.md`
**Last Updated:** November 6, 2025
**Status:** ✅ Ready for Review

---

## 🎉 Conclusion

This action plan provides a **clear, executable roadmap** for transforming Mister Munney from a prototype with critical security issues into a **production-ready, scalable, maintainable application**.

By following this plan over **10 weeks**, you will achieve:
- ✅ Secure authentication & authorization
- ✅ 85%+ test coverage
- ✅ Clean, maintainable codebase
- ✅ 75% faster performance
- ✅ Professional documentation

**The investment of 152 hours will pay dividends for years to come.**

Good luck with the implementation! 🚀
