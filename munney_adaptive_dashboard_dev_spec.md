# Mister Munney — Adaptive, Living Dashboard & Projects
**Development Assignment (for Claude Code)**  
Version: 1.0  
Owner: Piet / “Mister Munney”  
Date: 2025-11-03

> Goal: shift from rigid, limit‑driven budgets to a *living, behavior‑driven* dashboard with temporary **Projects** and support for **external (off‑ledger) payments**. Keep the current stack (Symfony + React + MySQL + Docker) and migrate incrementally behind feature flags.

---

## 0) TL;DR Scope
Implement the following features behind flags:
1. **Living Dashboard Visibility** — show only *active* budgets (activity in last 2–3 months or current period). Add “Show older budgets.”  
2. **Projects** — first‑class domain entity for temporary clusters (e.g., *Bathroom Renovation*), with totals across **linked bank transactions + external payments**.  
3. **External Payments** — quick entry form inside a project (amount, date, payer source, note, optional attachment) that contributes to project totals but not to account balances.  
4. **Behavioral Insights** — neutral, coach‑style cards per active budget (e.g., “+12% vs normal”) using rolling median/band; no hard alarms.  
5. **Budget Cards Refresh** — mini 6‑month sparkline, optional max line (only if set), filter chips (All / Active / Anomalies).  
6. **Refined Top Summary** — hide zero/noise tiles; add “Data analyzed through <date>.”  
7. **Accessibility & Performance** — keep pages lightweight, reduce color intensity, aim for calm visual language.

Non‑goals: changing underlying accounting rules, changing importers, multi‑currency, complex anomaly ML. Keep it simple and deterministic.

---

## 1) Product Requirements

### 1.1 User Stories
- **US‑1 (Dashboard Focus):** As a user, I only want to see budgets that are *currently* relevant so I’m not overwhelmed by historic clutter.
- **US‑2 (Recent Filter):** As a user, I want one click to reveal older budgets when needed.
- **US‑3 (Projects):** As a user, I want to group temporary expenses under a named project, see a running total, period, and category mix.
- **US‑4 (External Payments):** As a user, I want to add payments that happened outside tracked accounts (e.g., mortgage lender paid contractor) so project totals are complete.
- **US‑5 (Gentle Insights):** As a user, I want calm, neutral insights about trends vs my normal behavior—no shaming, just context.
- **US‑6 (Budget Cards):** As a user, I want each budget card to show a tiny trend and only show a max if I set one.
- **US‑7 (Performance):** As a user, the dashboard should render fast and feel light.
- **US‑8 (Trust):** As a user, I want transparency on what time range was analyzed.

### 1.2 Acceptance Criteria (AC)
- **AC‑1:** Dashboard lists budgets with transactions in current month **or** any of the previous **2** months (configurable via env). Others hidden under “Show older budgets” panel.
- **AC‑2:** “Older budgets” panel is collapsed by default and lists hidden budgets alphabetically with a count badge.
- **AC‑3:** New **Projects** section appears if ≥1 project is active or completed in selected period. Each card shows: Name, Period, Total, Split (Tracked / External / Other).
- **AC‑4:** In a project, I can add an **External Payment** with fields: amount, date, payer source (enum), note, optional file (PDF/JPG/PNG ≤ 10MB). It increases project total but **does not** affect account balances or budget spend outside the project context.
- **AC‑5:** Budget cards show a 6‑point sparkline (last 6 months), current value, “Normal” (rolling median of last 6 months), and **Max** if configured. No max line shown if not configured.
- **AC‑6:** Insights panel shows up to **3** neutral messages. Copy style: coaching, not punitive.
- **AC‑7:** Top summary hides tiles with zero values; adds subtle “Analyzed through <date>” right‑aligned.
- **AC‑8:** All new UI meets WCAG AA color contrast; keyboard navigable.
- **AC‑9:** P95 dashboard render time ≤ 1.2s (cold) on dev sample (10k txns).

---

## 2) Feature Specs

### 2.1 Living Dashboard Visibility
- **Definition of “Active budget”**: budget with ≥1 transaction in current month OR any of the previous N months (default N=2, `.env MUNNEY_ACTIVE_BUDGET_MONTHS=2`).
- **UI**:  
  - Section “Active this period” → grid of budget cards.  
  - Collapsible section below: “Older budgets (12)” → expands to list of non‑active ones.
- **Empty state**: show friendly message if no active budgets this period.

### 2.2 Projects (Temporary Clusters)
- **Entity**: `Project` with fields: id, name, description, start_date, end_date (nullable), status (active/completed/archived), created_at, updated_at.
- **Associations**:  
  - Link to categories (many‑to‑many) OR allow free association by attaching specific transactions.  
  - Project aggregates totals from: (a) linked transactions, (b) external payments.
- **UI**: Dedicated dashboard section with cards. Clicking a card → Project Detail page.
- **Project Detail** shows: header totals, period, categories used, list (or grouped) of entries (transactions + external). Two charts: monthly bars and cumulative line.

### 2.3 External Payments
- **Entity**: `ProjectExternalPayment` with: id, project_id, amount, currency, paid_on, payer_source (enum: `SELF`, `MORTGAGE_DEPOT`, `INSURER`, `OTHER`), note (text), attachment_url (nullable), created_at, updated_at.
- **Behavior**: contributes to project totals only.
- **Attach file**: upload to existing storage solution; return URL; virus‑scan hook optional (stubbed).
- **Audit**: record created_by user id; basic change log (amount, date, note edits).

### 2.4 Behavioral Insights
- **Computation**: per active budget, compute rolling *median* over last 6 complete months as “Normal” and compare current month to that. Add a light “band” (+/‑ 1 stddev) for contextual copy.  
- **Copy rules**:  
  - `|Δ| < 10%` → “Stabiel.”  
  - `10% ≤ Δ < 30%` → “Iets {hoger/lager} dan normaal.”  
  - `Δ ≥ 30%` → “Opvallend {hoger/lager} dan jouw gebruikelijke niveau.”  
- **Display**: max 3 insights in a calm panel (“Je maand in context”).

### 2.5 Budget Cards Refresh
- **Card anatomy**:  
  - Title + categories count (tooltip list).  
  - Current month total (big), small “Normaal: €X”.  
  - 6‑dot sparkline (last 6 months).  
  - Optional Max (thin line/label) **only if set**.  
  - Sub‑label (Stable / Slightly up / Down).  
- **Filters**: chips top‑right: `All | Active | Anomalies` (Anomalies = |Δ|≥30%).

### 2.6 Top Summary Strip
- Tiles: remove “Uncategorized €0” if zero; keep “Bestedbaar”, “Gecategoriseerd in budget”, “Resterend”.  
- Right: “Analyzed through 31 Oct 2025”.  
- Light background, less saturated colors.

---

## 3) Technical Plan (Backend & Frontend)

### 3.1 Feature Flags
- Introduce flags:  
  - `ff.living_dashboard`  
  - `ff.projects`  
  - `ff.external_payments`  
  - `ff.behavioral_insights`  
- Flags default ON in dev, OFF in prod. Universal middleware/React provider.

### 3.2 Backend (Symfony)
**Entities (Doctrine):**
- `Project` (see §2.2)  
- `ProjectExternalPayment` (see §2.3)

**Endpoints (prefix `/api/v1`)**
- **Budgets**
  - `GET /budgets/active?months=N` → active budgets + computed “normal”, sparkline series (6 months).  
  - `GET /budgets/older?months=N` → older budgets list.  
- **Projects**
  - `GET /projects` → list with totals split: tracked/external/other.  
  - `POST /projects` → create.  
  - `GET /projects/{id}` → detail with entries, series.  
  - `PATCH /projects/{id}` → edit meta.  
  - `POST /projects/{id}/external-payments` → create external.  
  - `PATCH /projects/external-payments/{paymentId}` → edit.  
  - `DELETE /projects/external-payments/{paymentId}` → soft‑delete.  
  - `POST /projects/{id}/attachments` (if needed for general docs).

**Services**  
- `ActiveBudgetService`: determine active/older based on txns.  
- `BudgetInsightsService`: compute rolling median/stddev(6m) + copy level.  
- `ProjectsAggregator`: compute totals + series; merge account txns + external payments.  
- `AttachmentStorage`: save and return URL; basic validation.

**Security**  
- All routes require auth; enforce per‑user/tenant scoping.

### 3.3 Frontend (React)
**State/Providers**
- `FeatureFlagProvider`
- `InsightsContext` (optional)

**Pages/Components**
- `DashboardPage` (new sections):  
  - `ActiveBudgetsGrid`  
  - `OlderBudgetsPanel` (collapsible)  
  - `ProjectsSection`  
  - `InsightsPanel`  
  - `TopSummaryStrip` (refined)

- `BudgetCard` (new): title, current, normal, sparkline, optional max.  
- `ProjectCard` and `ProjectDetail`: header totals; tabs: Overview | Entries | Files.  
- `ExternalPaymentForm` modal: amount, date, payer source, note, file.

**Visuals**
- Use existing design system; reduce saturation on alert colors; WCAG contrast checks.

**Analytics (optional)**
- `dashboard_view`, `older_panel_toggle`, `project_created`, `external_payment_added`.

---

## 4) Data & Migration

### 4.1 DB Migrations
- Create tables `project`, `project_external_payment`, pivot tables if linking categories/transactions.  
- Add minimal indices: `project(start_date)`, `project_external_payment(paid_on)`, FKs.  
- Soft‑delete column or status field for external payments.

### 4.2 Backfill / Integrity
- No mandatory backfill. Historical projects can be added later manually.  
- Budgets “normal” values are computed on the fly from existing transactions.

---

## 5) QA Plan

### 5.1 Unit/Integration
- Services: active/older budget calculation, insights thresholds, project aggregation, external payment inclusion/exclusion from balances.  
- Endpoints: permissions, validation, pagination.

### 5.2 E2E (Playwright/Cypress)
- Dashboard shows only active budgets.  
- Older budgets collapsible works.  
- Create project → add external payment → totals update; budget totals unchanged.  
- Insights display copy variants per Δ thresholds.  
- A11y: tab order, focus rings, ARIA labels.

### 5.3 Performance
- Seed with 10k transactions; dashboard API P95 ≤ 500ms; FE render ≤ 1.2s.

---

## 6) Rollout & Ops
- **Phase 1:** ship behind `ff.living_dashboard` and `ff.budget_cards_refresh`.  
- **Phase 2:** enable `ff.projects` and `ff.external_payments` for internal.  
- **Phase 3:** enable insights.  
- Add runtime flag controls via admin settings.  
- Track errors (Sentry), logs on all create/update routes.

---

## 7) Copy & Tone (Examples)
- “Stabiel.” / “Iets hoger dan normaal.” / “Opvallend lager dan jouw gebruikelijke niveau.”  
- “Externe betaling toegevoegd (bouwdepot).”  
- “Gegevens geanalyseerd t/m {{date}}.”  
- “Oudere budgetten verbergen/tonen.”

---

## 8) Definition of Done
- All AC in §1.2 met behind flags.  
- A11y checks pass (WCAG AA).  
- Automated tests in CI green.  
- No PII leaks in logs/attachments.  
- Product demo video/gif recorded and short README with how‑to.

---

## 9) Developer Checklist (Claude Code)
- [ ] Add feature flags (BE + FE).  
- [ ] Add entities + migrations for `Project`, `ProjectExternalPayment`.  
- [ ] Implement `ActiveBudgetService`, `BudgetInsightsService`, `ProjectsAggregator`.  
- [ ] Expose endpoints (§3.2).  
- [ ] Build React components (§3.3).  
- [ ] Wire upload for external payment file.  
- [ ] Implement insights copy rules.  
- [ ] A11y & performance passes.  
- [ ] Unit/E2E tests per §5.  
- [ ] Update docs + demo.

---

## 10) Nice‑to‑Have (later)
- Auto‑suggest project creation when clusters of categories spike.  
- Per‑project pie: split by funding source (Tracked vs External vs Other).  
- CSV import for historical external payments.  
- Calendar view for project spend timeline.

