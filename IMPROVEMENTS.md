# Munney App Improvements

## Quick Wins (1-3 hours each) - COMPLETED

- [x] **Add React Error Boundary** (1-2h)
  - Created `ErrorBoundary.tsx` component
  - Added fallback UI with retry/reload options
  - Wrapped app in error boundary in `main.tsx`

- [x] **Add security headers to Nginx** (1h)
  - Added Content-Security-Policy
  - Added Referrer-Policy
  - Added Permissions-Policy
  - (X-Frame-Options, X-Content-Type-Options already existed)

- [x] **Add form validation with Zod** (2-3h)
  - Installed Zod library
  - Created validation schemas (`shared/validation/schemas.ts`)
  - Created `useFormValidation` hook
  - Updated AuthScreen and CreateBudgetModal to use Zod

- [x] **Fix TypeScript `any` types** (2-3h)
  - Created `errorUtils.ts` with type guards
  - Fixed AuthContext to use proper error handling
  - Fixed PatternService and TransactionsService types

- [x] **Database indexes audit** (1h)
  - Added composite index on `transaction(account_id, date)`
  - Added index on `transaction(date)`
  - Added index on `transaction(transaction_type)`

- [x] **Standardize API error format** (2-3h)
  - Created `ApiErrorResponse` class with factory methods
  - Updated `ApiExceptionListener` to use standardized format
  - Error format now includes: success, error.code, error.message, meta.timestamp

---

## Bigger Improvements

- [x] **Add comprehensive DTO validation** (3-4h)
  - Added Symfony validation constraints to all input DTOs
  - All error messages translated to Dutch
  - DTOs updated:
    - `CreatePatternDTO` - Added length limits, choice constraints, Dutch messages
    - `UpdatePatternDTO` - Added length limits, choice constraints, Dutch messages
    - `CreateProjectDTO` - Added name length, description max, duration range (1-120 months)
    - `UpdateProjectDTO` - Added validation for all optional fields
    - `CreateExternalPaymentDTO` - Added amount max (10M), direction choices
    - `TransactionFilterDTO` - Added cross-field validation (date/amount ranges)
    - `SetCategoryDTO` - Added positive constraint with Dutch message
    - `AcceptPatternSuggestionDTO` - Added length limits, hex color regex
    - `AssignPatternDateRangeDTO` - Added Dutch messages for NotBlank/Date
    - `CreateBudgetDTO` - Added categoryIds array validation, icon length
    - `UpdateBudgetDTO` - Added Dutch messages for length constraints

- [ ] **Increase test coverage to 80%** (1-2 days)
  - Add unit tests for services
  - Add integration tests for API endpoints
  - Add more E2E Playwright tests
  - Enable integration tests in CI

- [ ] **Implement code splitting** (3-4h)
  - Lazy load route components
  - Split vendor chunks
  - Add loading states for lazy components

- [ ] **Add structured logging** (4-6h)
  - Configure JSON log format
  - Add request/response logging
  - Log critical business events
  - Add correlation IDs

- [ ] **API versioning + pagination** (1 day)
  - Add `/api/v1/` prefix
  - Implement cursor-based pagination
  - Add pagination metadata to responses
  - Document pagination in OpenAPI

---

## Security Enhancements

- [ ] **Add audit logging** (2-3h)
  - Log login attempts
  - Log sensitive data changes
  - Log admin actions

- [ ] **Add request size limits** (1h)
  - Configure Nginx limits
  - Add Symfony request size validation

- [ ] **Security headers testing** (1h)
  - Test with securityheaders.com
  - Fix any issues found

---

## Performance Optimizations

- [ ] **React performance audit** (2-3h)
  - Add React.memo to expensive components
  - Optimize useCallback/useMemo usage
  - Profile with React DevTools

- [ ] **Database query optimization** (3-4h)
  - Audit N+1 queries
  - Add eager loading where missing
  - Consider query result caching

- [ ] **Add caching layer** (4-6h)
  - Add Redis for session/cache
  - Cache expensive API responses
  - Add HTTP cache headers

---

## Observability

- [ ] **Add health check endpoints** (1h)
  - Database connectivity check
  - External service checks
  - Version info endpoint

- [ ] **Add metrics collection** (3-4h)
  - Response time metrics
  - Error rate tracking
  - Business metrics (transactions/day, etc.)

- [ ] **Consider APM integration** (2-3h)
  - Evaluate Sentry, New Relic, or DataDog
  - Implement chosen solution

---

## Progress Tracking

| Category | Total | Completed | Progress |
|----------|-------|-----------|----------|
| Quick Wins | 6 | 6 | 100% |
| Bigger Improvements | 5 | 1 | 20% |
| Security | 3 | 0 | 0% |
| Performance | 3 | 0 | 0% |
| Observability | 3 | 0 | 0% |

**Last Updated:** 2025-12-05
