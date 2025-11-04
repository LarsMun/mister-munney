# Adaptive Dashboard Implementation Plan
**Feature Branch**: `feature/adaptive-dashboard`
**Start Date**: 2025-11-03
**Status**: Planning Complete, Ready to Implement

---

## Architecture Decision

After analysis, we decided to **extend Budget entity** rather than create a separate Project entity:

### Key Decisions
- ✅ Extend `BudgetType` enum with `PROJECT` type
- ✅ Add temporal fields to Budget (startDate, endDate, status)
- ✅ Create new `ExternalPayment` entity (linked to PROJECT-type budgets)
- ✅ Behavioral insights apply ONLY to EXPENSE/INCOME budgets
- ✅ Projects are budgets with type=PROJECT, displayed separately in UI

### Benefits
- Reuses existing Budget infrastructure (BudgetVersion, Categories)
- Patterns work naturally (categories auto-assign to project budgets)
- No parallel hierarchies (Transaction → Category → Budget always)
- Clean separation in UI (filter by budgetType)

---

## Implementation Phases

### Phase 1: Database & Entity Setup
**Goal**: Extend data model to support projects as budget subtype

**Tasks**:
1. Add `PROJECT` value to `BudgetType` enum
2. Extend `Budget` entity:
   - Add `startDate` (DateTimeImmutable, nullable)
   - Add `endDate` (DateTimeImmutable, nullable)
   - Add `status` (ProjectStatus enum, nullable)
3. Create `ProjectStatus` enum: `ACTIVE`, `COMPLETED`, `ARCHIVED`
4. Create `PayerSource` enum: `SELF`, `MORTGAGE_DEPOT`, `INSURER`, `OTHER`
5. Create `ExternalPayment` entity:
   - `budget_id` (ManyToOne → Budget, must be type PROJECT)
   - `amountInCents` (int)
   - `paidOn` (DateTimeImmutable)
   - `payerSource` (PayerSource enum)
   - `note` (text)
   - `attachmentUrl` (string, nullable)
   - `createdAt`, `updatedAt`
6. Generate migration, review, and execute

**Files to modify**:
- `backend/src/Enum/BudgetType.php`
- `backend/src/Enum/ProjectStatus.php` (new)
- `backend/src/Enum/PayerSource.php` (new)
- `backend/src/Entity/Budget.php`
- `backend/src/Entity/ExternalPayment.php` (new)

**Acceptance**:
- [ ] Migration runs without errors
- [ ] Can create Budget with type=PROJECT
- [ ] Can add ExternalPayment to PROJECT budget
- [ ] Database constraints enforced

---

### Phase 2: Feature Flags
**Goal**: Create runtime-toggleable feature flag system

**Tasks**:
1. Create `FeatureFlag` entity (name, enabled, description)
2. Create migration for `feature_flag` table
3. Create `FeatureFlagService`:
   - Check env vars first: `FEATURE_LIVING_DASHBOARD=true`
   - Fallback to database table
   - Cache flags in memory per request
4. Seed initial flags (all ON in dev):
   - `living_dashboard`
   - `projects`
   - `external_payments`
   - `behavioral_insights`

**Files to create**:
- `backend/src/Entity/FeatureFlag.php`
- `backend/src/Service/FeatureFlagService.php`
- `backend/src/DataFixtures/FeatureFlagFixtures.php` (optional)

**Acceptance**:
- [ ] Can check `featureFlagService->isEnabled('projects')`
- [ ] Env vars override database
- [ ] All flags default ON in dev environment

---

### Phase 3: Backend Services
**Goal**: Implement business logic for adaptive dashboard

#### ActiveBudgetService
**Responsibility**: Determine which budgets are "active" vs "older"

**Logic**:
- **EXPENSE/INCOME**: active if ≥1 transaction in last N months (default 2)
- **PROJECT**: active if status=ACTIVE OR (today between startDate-endDate)

**Methods**:
```php
getActiveBudgets(int $months = 2, ?BudgetType $type = null): array
getOlderBudgets(int $months = 2, ?BudgetType $type = null): array
isActive(Budget $budget, int $months = 2): bool
```

#### BudgetInsightsService
**Responsibility**: Compute behavioral insights for EXPENSE/INCOME budgets

**Logic**:
- For each active EXPENSE/INCOME budget:
  - Compute rolling median over last 6 complete months ("Normal")
  - Compute standard deviation for context band
  - Compare current month to median
  - Generate neutral copy based on Δ percentage

**Copy rules**:
- `|Δ| < 10%` → "Stabiel."
- `10% ≤ Δ < 30%` → "Iets hoger/lager dan normaal."
- `Δ ≥ 30%` → "Opvallend hoger/lager dan jouw gebruikelijke niveau."

**Methods**:
```php
computeInsights(?int $limit = 3): array
computeNormal(Budget $budget, int $months = 6): Money
getSparklineData(Budget $budget, int $months = 6): array
```

**Output**:
```php
[
    'budgetId' => 5,
    'budgetName' => 'Boodschappen',
    'current' => '€523',
    'normal' => '€450',
    'delta' => '+16%',
    'message' => 'Iets hoger dan normaal.',
    'sparkline' => [450, 420, 480, 445, 460, 523]
]
```

#### ProjectAggregatorService
**Responsibility**: Aggregate totals for PROJECT-type budgets

**Logic**:
- Sum transactions linked via categories (tracked spend)
- Sum external payments
- Calculate total = tracked + external
- Group by month for charts
- Cumulative line data

**Methods**:
```php
getProjectTotals(Budget $project): ProjectTotals
getProjectEntries(Budget $project): array
getProjectTimeSeries(Budget $project): array
```

**Output**:
```php
[
    'tracked' => '€2,500',      // From bank transactions
    'external' => '€8,000',     // From external payments
    'total' => '€10,500',
    'categoryBreakdown' => [...],
    'monthlyBars' => [...],
    'cumulativeLine' => [...]
]
```

#### AttachmentStorageService
**Responsibility**: Handle file uploads for external payments

**Logic**:
- Store in `/backend/var/uploads/external-payments/{year}/{month}/{uuid}.{ext}`
- Validate file type (PDF, JPG, PNG)
- Validate file size (≤ 10MB)
- Return public URL for storage
- Optional: virus scan stub (placeholder)

**Methods**:
```php
store(UploadedFile $file, int $externalPaymentId): string
validate(UploadedFile $file): void
delete(string $url): void
```

**Files to create**:
- `backend/src/Budget/Service/ActiveBudgetService.php`
- `backend/src/Budget/Service/BudgetInsightsService.php`
- `backend/src/Budget/Service/ProjectAggregatorService.php`
- `backend/src/Budget/Service/AttachmentStorageService.php`

---

### Phase 4: Backend API Endpoints
**Goal**: Expose new functionality via REST API

**Endpoints to add/modify**:

#### Budgets (EXPENSE/INCOME)
- **GET** `/api/budgets/active?months=2&type=EXPENSE,INCOME`
  - Returns active budgets with insights, sparklines, "normal" values
  - Feature flag: `living_dashboard`, `behavioral_insights`

- **GET** `/api/budgets/older?months=2`
  - Returns non-active budgets (simple list with count)
  - Feature flag: `living_dashboard`

#### Projects (PROJECT-type budgets)
- **GET** `/api/budgets?type=PROJECT&status=ACTIVE`
  - Returns project budgets with totals split (tracked/external)
  - Feature flag: `projects`

- **POST** `/api/budgets`
  - Body: `{name, description, budgetType: "PROJECT", startDate, endDate}`
  - Feature flag: `projects`

- **GET** `/api/budgets/{id}/details`
  - Returns project details with entries, time series, category breakdown
  - Feature flag: `projects`

- **PATCH** `/api/budgets/{id}`
  - Update project meta (name, dates, status)

#### External Payments
- **POST** `/api/budgets/{budgetId}/external-payments`
  - Body: `{amount, paidOn, payerSource, note}`
  - Validates budget is type=PROJECT
  - Feature flag: `external_payments`

- **PATCH** `/api/external-payments/{id}`
  - Update external payment

- **DELETE** `/api/external-payments/{id}`
  - Soft-delete external payment

- **POST** `/api/external-payments/{id}/attachment`
  - Multipart file upload
  - Returns attachment URL

**Files to create/modify**:
- `backend/src/Budget/Controller/BudgetController.php` (extend)
- `backend/src/Budget/Controller/ExternalPaymentController.php` (new)
- `backend/src/Budget/DTO/ProjectDetailsDTO.php` (new)
- `backend/src/Budget/DTO/ExternalPaymentDTO.php` (new)
- `backend/src/Budget/DTO/BudgetInsightsDTO.php` (new)

**Acceptance**:
- [ ] All endpoints return correct data
- [ ] Feature flags properly gate access
- [ ] Validation works (PROJECT budget required for external payments)
- [ ] Error handling returns proper status codes

---

### Phase 5: Frontend Infrastructure
**Goal**: Setup React foundation for new features

**Tasks**:
1. Create `FeatureFlagProvider` React context
2. Create `useFeatureFlag(flagName)` hook
3. Fetch flags from backend on app load
4. Add API service functions in `lib/api.ts`:
   - `getActiveBudgets(months?)`
   - `getOlderBudgets(months?)`
   - `getProjects(status?)`
   - `createProject(data)`
   - `getProjectDetails(id)`
   - `createExternalPayment(budgetId, data)`
   - `updateExternalPayment(id, data)`
   - `deleteExternalPayment(id)`
   - `uploadAttachment(paymentId, file)`

5. Create TypeScript types:
   - `Budget` (extend with startDate, endDate, status, budgetType)
   - `ExternalPayment`
   - `BudgetInsight`
   - `ProjectTotals`
   - `PayerSource` enum

**Files to create**:
- `frontend/src/shared/contexts/FeatureFlagContext.tsx`
- `frontend/src/shared/hooks/useFeatureFlag.ts`
- `frontend/src/domains/budgets/models/Budget.ts` (extend)
- `frontend/src/domains/budgets/models/ExternalPayment.ts`
- `frontend/src/domains/budgets/models/Insight.ts`
- `frontend/src/domains/budgets/services/budgetApi.ts`

**Acceptance**:
- [ ] `useFeatureFlag('projects')` returns boolean
- [ ] API calls work with proper TypeScript types
- [ ] Feature flags gate UI components

---

### Phase 6: Dashboard UI (Living Dashboard)
**Goal**: Refactor dashboard to show only active budgets with insights

**Layout**:
```
┌─────────────────────────────────────────────────┐
│ Top Summary Strip                                │
│ Bestedbaar | Gecategoriseerd | Resterend        │
│                     Analyzed through 31 Oct 2025 │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ Insights Panel (max 3)                          │
│ 🔍 Boodschappen: Iets hoger dan normaal (+16%)  │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ Active Budgets (Filters: All | Active | Anomalies)│
│ ┌──────────┐ ┌──────────┐ ┌──────────┐         │
│ │Budget    │ │Budget    │ │Budget    │         │
│ │Card      │ │Card      │ │Card      │         │
│ └──────────┘ └──────────┘ └──────────┘         │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ ▶ Older budgets (12)                [collapsed] │
└─────────────────────────────────────────────────┘
```

**BudgetCard anatomy**:
```
┌─────────────────────────┐
│ Boodschappen (3) ⓘ      │  ← Title + category count
│                         │
│ €523                    │  ← Current month total (big)
│ Normaal: €450           │  ← Rolling median
│ ●●●●●● (sparkline)      │  ← 6-month trend
│ Max: €600 ─── (optional)│  ← Only if set
│                         │
│ Iets hoger dan normaal  │  ← Insight label
└─────────────────────────┘
```

**Components to create**:
- `TopSummaryStrip` (refactor existing)
  - Hide tiles with €0 values
  - Add right-aligned "Analyzed through {date}"
  - Reduce color saturation

- `InsightsPanel`
  - Max 3 insights
  - Neutral, coaching tone
  - Icon per insight type

- `ActiveBudgetsGrid`
  - Filter chips: All | Active | Anomalies
  - Grid layout (responsive)

- `BudgetCard`
  - Title with category count tooltip
  - Current value (large)
  - Normal value (small)
  - 6-point sparkline (Recharts mini chart)
  - Optional max line
  - Insight label (stable/up/down)

- `OlderBudgetsPanel`
  - Collapsible (default closed)
  - Badge with count
  - Alphabetical list

**Files to create**:
- `frontend/src/domains/budgets/components/TopSummaryStrip.tsx`
- `frontend/src/domains/budgets/components/InsightsPanel.tsx`
- `frontend/src/domains/budgets/components/ActiveBudgetsGrid.tsx`
- `frontend/src/domains/budgets/components/BudgetCard.tsx`
- `frontend/src/domains/budgets/components/BudgetSparkline.tsx`
- `frontend/src/domains/budgets/components/OlderBudgetsPanel.tsx`
- `frontend/src/domains/budgets/DashboardPage.tsx` (refactor)

**Acceptance**:
- [ ] Only active budgets shown by default
- [ ] Older budgets hidden under collapsible
- [ ] Insights panel shows max 3 items
- [ ] Sparklines render correctly
- [ ] Max line only shows if configured
- [ ] Filter chips work (All/Active/Anomalies)
- [ ] Mobile responsive

---

### Phase 7: Projects UI
**Goal**: Create project management interface

**Layout**:
```
Dashboard → Projects Section
┌─────────────────────────────────────────────────┐
│ Projects                            [+ New]      │
│ ┌──────────┐ ┌──────────┐                       │
│ │Bathroom  │ │Solar     │                       │
│ │Reno      │ │Panels    │                       │
│ │€10,500   │ │€5,200    │                       │
│ │Tracked:  │ │Tracked:  │                       │
│ │€2,500    │ │€5,200    │                       │
│ │External: │ │External: │                       │
│ │€8,000    │ │€0        │                       │
│ └──────────┘ └──────────┘                       │
└─────────────────────────────────────────────────┘

Click project → Project Detail Page
┌─────────────────────────────────────────────────┐
│ ← Back    Bathroom Renovation                   │
│ Jan 2025 - Mar 2025 | ACTIVE                    │
│                                                  │
│ Total: €10,500 | Tracked: €2,500 | External: €8,000│
│                                [+ External Payment] │
│                                                  │
│ Tabs: [Overview] [Entries] [Files]              │
│                                                  │
│ [Overview tab content: charts, breakdown]       │
└─────────────────────────────────────────────────┘
```

**Components**:
- `ProjectsSection` (on dashboard)
  - Grid of project cards
  - Feature flag gated
  - "New Project" button

- `ProjectCard`
  - Name, period (Jan-Mar 2025)
  - Status badge (Active/Completed/Archived)
  - Total with split (Tracked/External)
  - Click → navigate to detail

- `ProjectDetailPage`
  - Header: name, period, status, totals
  - Tabs: Overview | Entries | Files
  - Overview: monthly bar chart, cumulative line, category pie
  - Entries: table of transactions + external payments (merged, sorted by date)
  - Files: grid of attachments from external payments

- `ProjectCreateForm` (modal)
  - Name, description
  - Start date, end date (optional)
  - Create as active by default

- `ExternalPaymentForm` (modal)
  - Amount (EUR)
  - Date paid
  - Payer source (dropdown)
  - Note (textarea)
  - File upload (PDF/JPG/PNG, ≤10MB)
  - Preview uploaded file

**Files to create**:
- `frontend/src/domains/budgets/components/ProjectsSection.tsx`
- `frontend/src/domains/budgets/components/ProjectCard.tsx`
- `frontend/src/domains/budgets/ProjectDetailPage.tsx`
- `frontend/src/domains/budgets/components/ProjectCreateForm.tsx`
- `frontend/src/domains/budgets/components/ExternalPaymentForm.tsx`
- `frontend/src/domains/budgets/components/ProjectOverviewTab.tsx`
- `frontend/src/domains/budgets/components/ProjectEntriesTab.tsx`
- `frontend/src/domains/budgets/components/ProjectFilesTab.tsx`

**Acceptance**:
- [ ] Projects section appears on dashboard (if ≥1 project)
- [ ] Can create new project
- [ ] Project detail shows correct totals
- [ ] Can add external payment
- [ ] Can upload attachment
- [ ] Charts render correctly
- [ ] Entries table merges transactions + external payments

---

### Phase 8: Testing
**Goal**: Ensure reliability through automated tests

**Backend Tests** (PHPUnit):

1. **ActiveBudgetService**
   - Active budget detection for EXPENSE/INCOME (with/without recent transactions)
   - Active budget detection for PROJECT (by date range and status)
   - Older budget filtering

2. **BudgetInsightsService**
   - Rolling median calculation (6 months)
   - Standard deviation calculation
   - Delta percentage calculation
   - Copy generation (Stabiel / Iets hoger / Opvallend lager)
   - Only processes EXPENSE/INCOME (skips PROJECT)

3. **ProjectAggregatorService**
   - Totals calculation (tracked + external)
   - Category breakdown
   - Monthly time series
   - Cumulative totals

4. **API Endpoints** (Integration tests)
   - GET /api/budgets/active returns correct budgets with insights
   - GET /api/budgets/older returns non-active budgets
   - POST /api/budgets creates PROJECT budget
   - POST /api/budgets/{id}/external-payments validates PROJECT type
   - POST /api/budgets/{id}/external-payments adds to totals
   - File upload validation (size, type)

**Files to create**:
- `backend/tests/Unit/Budget/Service/ActiveBudgetServiceTest.php`
- `backend/tests/Unit/Budget/Service/BudgetInsightsServiceTest.php`
- `backend/tests/Unit/Budget/Service/ProjectAggregatorServiceTest.php`
- `backend/tests/Integration/Budget/BudgetApiTest.php`
- `backend/tests/Integration/Budget/ExternalPaymentApiTest.php`

**Acceptance**:
- [ ] All tests pass
- [ ] Coverage ≥ 80% for new services
- [ ] Edge cases covered (no data, zero amounts, etc.)

---

### Phase 9: Accessibility & Polish
**Goal**: Ensure WCAG AA compliance and calm visual design

**Tasks**:
1. **Color contrast checks**
   - Run automated contrast checker on all new components
   - Ensure text on backgrounds meets 4.5:1 minimum
   - Reduce saturation on alert colors (less intense reds/greens)

2. **Keyboard navigation**
   - Tab order follows visual flow
   - Focus rings visible and styled
   - Modal traps focus correctly
   - Escape closes modals

3. **ARIA labels**
   - Buttons have aria-label if icon-only
   - Charts have aria-label summaries
   - Collapsible panels have aria-expanded
   - Form fields have aria-describedby for errors

4. **Visual polish**
   - Reduce color saturation (calm palette)
   - Consistent spacing (Tailwind scale)
   - Loading states (skeletons)
   - Empty states with helpful messages
   - Error states with recovery actions

**Tools**:
- axe DevTools browser extension
- Lighthouse accessibility audit
- Manual keyboard testing

**Acceptance**:
- [ ] Lighthouse accessibility score ≥ 95
- [ ] All interactive elements keyboard accessible
- [ ] ARIA labels on all custom components
- [ ] Color contrast meets WCAG AA
- [ ] Visual design feels calm, not alarming

---

### Phase 10: Performance & Documentation
**Goal**: Ensure fast dashboard and update docs

**Performance Tasks**:
1. Seed test database with 10k transactions
2. Measure P95 render time for dashboard (target: ≤ 1.2s cold)
3. Optimize queries if needed (add indexes, eager loading)
4. API response time target: P95 ≤ 500ms

**Documentation Tasks**:
1. Update `CLAUDE.md`:
   - Add Projects section to architecture
   - Document Budget.budgetType = PROJECT pattern
   - Update entity relationships
   - Add feature flag usage

2. Update `README.md`:
   - Add feature flag environment variables
   - Document new endpoints
   - Add screenshots/demo

3. Create demo materials:
   - Screenshot of living dashboard
   - Screenshot of project detail
   - Short video walkthrough (optional)

**Acceptance**:
- [ ] Dashboard loads in ≤ 1.2s with 10k transactions
- [ ] API endpoints respond in ≤ 500ms (P95)
- [ ] CLAUDE.md updated
- [ ] README.md updated
- [ ] Demo screenshots captured

---

## Rollout Plan

**Phase 1 (Internal Testing)**:
- Enable all flags in dev environment
- Test with real data
- Gather feedback

**Phase 2 (Beta)**:
- Enable `living_dashboard` and `behavioral_insights` in prod
- Monitor performance and errors
- Iterate on insights copy

**Phase 3 (Full Release)**:
- Enable `projects` and `external_payments`
- Full announcement
- Create user guide

---

## Technical Assumptions (Confirmed)

✅ **Auth**: Skip for now, add TODO comments for future
✅ **Projects linking**: Transaction → Category → Budget (type=PROJECT)
✅ **File storage**: Local filesystem `/backend/var/uploads/`
✅ **Feature flags**: Env-based + database for runtime toggle
✅ **API versioning**: Keep `/api/*` (no `/api/v1/`)
✅ **E2E testing**: Defer, focus on PHPUnit
✅ **Currency**: Hardcode EUR

---

## Notes & Decisions Log

### 2025-11-03: Architecture Alignment
- **Decision**: Extend Budget instead of creating separate Project entity
- **Rationale**:
  - Reuses existing infrastructure (BudgetVersion, Categories, Patterns)
  - Avoids parallel hierarchies
  - Patterns work naturally for project categorization
  - Clean separation via budgetType filter
- **Impact**: Simplified implementation, fewer new tables, better maintainability

### Feature Flag Strategy
- **Decision**: Env vars with database fallback
- **Rationale**: Easy local dev (env vars), runtime control in prod (database)
- **Implementation**: Check env first, then DB, cache in memory

### Behavioral Insights Scope
- **Decision**: Only apply to EXPENSE/INCOME budgets, exclude PROJECT
- **Rationale**: Projects are one-off, no "normal" baseline to compare against
- **Implementation**: Filter by budgetType in BudgetInsightsService

---

## Progress Tracking

All tasks tracked via Claude Code TodoWrite tool. Check todo list for real-time status.

**Current Phase**: Planning Complete
**Next Step**: Begin Phase 1 (Database & Entity Setup)

---

## Questions / Blockers

None currently. Ready to proceed.

---

**Last Updated**: 2025-11-03
**Document Owner**: Claude Code + Lars
