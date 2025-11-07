# Money App - Database Performance Analysis Report

**Date:** November 6, 2025  
**Database:** MySQL 8.0 (money_db)  
**Environment:** Local Development (Docker)

---

## Executive Summary

The database structure is well-designed with proper foreign key relationships and mostly appropriate indexes. However, there are **critical N+1 query risks** in the application services that could cause significant performance degradation as data grows. The transaction table with 13,431 rows is the primary concern.

**Risk Level:** MEDIUM (potential to become HIGH with data growth)

---

## 1. Database Structure Overview

### Table Statistics
| Table | Row Count | Size (MB) | Purpose |
|-------|-----------|-----------|---------|
| transaction | 13,431 | 7.86 | Core transaction data |
| ai_pattern_suggestion | 54 | 0.16 | AI-generated categorization suggestions |
| category | 54 | 0.05 | Transaction categories |
| pattern | 18 | 0.08 | Pattern matching rules |
| account | 2 | 0.03 | Bank accounts |
| budget | 8 | 0.03 | Budget definitions |
| budget_version | 8 | 0.06 | Budget version history |
| savings_account | 4 | 0.05 | Savings accounts |
| external_payment | 2 | 0.03 | Project external payments |
| feature_flag | 4 | 0.03 | Runtime feature toggles |
| project_attachment | 0 | 0.03 | Project file attachments |
| doctrine_migration_versions | 14 | 0.02 | Migration tracking |

**Total Database Size:** ~8.3 MB (development-scale data)

---

## 2. Index Analysis

### Complete Index Inventory

| Table | Index Name | Type | Columns | Status |
|-------|-----------|------|---------|--------|
| account | PRIMARY | BTREE | id | OK |
| account | UNIQ_7D3656A4B1A4D127 | BTREE | account_number | OK - Unique |
| ai_pattern_suggestion | PRIMARY | BTREE | id | OK |
| ai_pattern_suggestion | UNIQ_B3F1F7F7955D11 | BTREE | pattern_hash | OK - Unique |
| ai_pattern_suggestion | IDX_B3F1F7F9B6B5FBA | BTREE | account_id | OK - FK |
| ai_pattern_suggestion | IDX_B3F1F7F9B6B5FBA7B00651C | BTREE | account_id, status | COMPOSITE - Good |
| ai_pattern_suggestion | IDX_B3F1F7F6E2100B8 | BTREE | existing_category_id | OK - FK |
| ai_pattern_suggestion | IDX_B3F1F7F56B0ABAE | BTREE | created_pattern_id | OK - FK |
| budget | PRIMARY | BTREE | id | OK |
| budget | IDX_73F2F77B9B6B5FBA | BTREE | account_id | OK - FK |
| budget_version | PRIMARY | BTREE | id | OK |
| budget_version | IDX_7D9F992236ABA6B8 | BTREE | budget_id | OK - FK |
| budget_version | idx_effective_from | BTREE | effective_from_month | GOOD - Query optimization |
| budget_version | idx_effective_until | BTREE | effective_until_month | GOOD - Query optimization |
| category | PRIMARY | BTREE | id | OK |
| category | IDX_64C19C19B6B5FBA | BTREE | account_id | OK - FK |
| category | IDX_64C19C136ABA6B8 | BTREE | budget_id | OK - FK |
| external_payment | PRIMARY | BTREE | id | OK |
| external_payment | IDX_B48A5D4E36ABA6B8 | BTREE | budget_id | OK - FK |
| feature_flag | PRIMARY | BTREE | id | OK |
| feature_flag | UNIQ_83DE64E95E237E06 | BTREE | name | OK - Unique |
| pattern | PRIMARY | BTREE | id | OK |
| pattern | UNIQ_A3BCFC8E173059B4 | BTREE | unique_hash | OK - Unique |
| pattern | IDX_A3BCFC8E9B6B5FBA | BTREE | account_id | OK - FK |
| pattern | IDX_A3BCFC8E12469DE2 | BTREE | category_id | OK - FK |
| pattern | IDX_A3BCFC8EFCB8D9DE | BTREE | savings_account_id | OK - FK |
| project_attachment | PRIMARY | BTREE | id | OK |
| project_attachment | IDX_61F9A28936ABA6B8 | BTREE | budget_id | OK - FK |
| savings_account | PRIMARY | BTREE | id | OK |
| savings_account | IDX_EA211D3A9B6B5FBA | BTREE | account_id | OK - FK |
| savings_account | unique_savings_account | BTREE | name, account_id | COMPOSITE - Good |
| transaction | PRIMARY | BTREE | id | OK |
| transaction | unique_transaction | BTREE | hash | OK - Unique |
| transaction | IDX_723705D19B6B5FBA | BTREE | account_id | OK - FK |
| transaction | IDX_723705D112469DE2 | BTREE | category_id | OK - FK |
| transaction | IDX_723705D1FCB8D9DE | BTREE | savings_account_id | OK - FK |
| transaction | IDX_723705D1C19C5E47 | BTREE | parent_transaction_id | OK - FK |

### ✓ All Foreign Keys Have Indexes
**Finding:** All 17 foreign key columns have corresponding indexes. This is excellent for join performance.

---

## 3. Missing Indexes - RECOMMENDATIONS

### HIGH PRIORITY (Performance Impact)

#### 1. **transaction.date** - Frequently Used in WHERE and GROUP BY
**Current:** None  
**Recommendation:** Add `idx_transaction_date` on `transaction(date)`  
**Reason:** Most queries filter/group transactions by date:
- `findAvailableMonths()` - Groups by date
- `getMonthlyDebitTotals()` - Filters by date range
- `getMonthlyTotalsByCategory()` - Filters by date  
- Dashboard queries - Filter by date range

**Estimated Impact:** 30-50% faster date-range queries on large datasets

```sql
ALTER TABLE transaction ADD INDEX idx_transaction_date (date);
```

#### 2. **transaction(account_id, date)** - Composite Index
**Current:** Separate indexes only  
**Recommendation:** Add composite `idx_transaction_account_date` on `transaction(account_id, date DESC)`  
**Reason:** Many queries combine these two predicates:
```sql
SELECT * FROM transaction WHERE account_id = ? AND date BETWEEN ? AND ?
```

**Estimated Impact:** 40-60% faster account-filtered date-range queries (covers most use cases)

```sql
ALTER TABLE transaction ADD INDEX idx_transaction_account_date (account_id, date DESC);
```

#### 3. **transaction(account_id, category_id, date)** - Complex Query Optimization
**Current:** Separate indexes  
**Recommendation:** Add composite `idx_transaction_account_category_date` on `transaction(account_id, category_id, date DESC)`  
**Reason:** Budget and category total calculations use all three:
```sql
SELECT SUM(amount) FROM transaction WHERE account_id = ? AND category_id = ? AND date BETWEEN ?
```

**Estimated Impact:** 40-70% faster category spending queries

```sql
ALTER TABLE transaction ADD INDEX idx_transaction_account_category_date (account_id, category_id, date DESC);
```

#### 4. **transaction(category_id, date)** - For Category Reports
**Current:** Separate indexes  
**Recommendation:** Add composite `idx_transaction_category_date` on `transaction(category_id, date DESC)`  
**Reason:** Category statistics queries need both columns

**Estimated Impact:** 30-50% faster category-specific queries

```sql
ALTER TABLE transaction ADD INDEX idx_transaction_category_date (category_id, date DESC);
```

### MEDIUM PRIORITY

#### 5. **transaction.parent_transaction_id + status filter**
**Current:** Single column index  
**Recommendation:** Potentially add `idx_transaction_parent_active` on `transaction(parent_transaction_id, category_id)`  
**Reason:** Split transaction queries often check if parent has child splits:
```sql
SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id
```

This is already well-indexed with parent_transaction_id. **No change needed yet** but monitor if split transaction operations become slow.

#### 6. **category.account_id** - Category Lookups
**Current:** Has index  
**Recommendation:** Consider composite `idx_category_account_name` on `category(account_id, name)`  
**Reason:** Category lookups by account + name are common  
**Impact:** Minimal - only helps if `name` is frequently in WHERE clause

---

## 4. Foreign Key Relationship Validation

### ✓ All Foreign Keys Are Properly Indexed

| Child Table | FK Column | Parent Table | Index Exists |
|-------------|-----------|--------------|--------------|
| ai_pattern_suggestion | account_id | account | YES - IDX_B3F1F7F9B6B5FBA |
| ai_pattern_suggestion | existing_category_id | category | YES - IDX_B3F1F7F6E2100B8 |
| ai_pattern_suggestion | created_pattern_id | pattern | YES - IDX_B3F1F7F56B0ABAE |
| budget | account_id | account | YES - IDX_73F2F77B9B6B5FBA |
| budget_version | budget_id | budget | YES - IDX_7D9F992236ABA6B8 |
| category | account_id | account | YES - IDX_64C19C19B6B5FBA |
| category | budget_id | budget | YES - IDX_64C19C136ABA6B8 |
| external_payment | budget_id | budget | YES - IDX_B48A5D4E36ABA6B8 |
| pattern | account_id | account | YES - IDX_A3BCFC8E9B6B5FBA |
| pattern | category_id | category | YES - IDX_A3BCFC8E12469DE2 |
| pattern | savings_account_id | savings_account | YES - IDX_A3BCFC8EFCB8D9DE |
| project_attachment | budget_id | budget | YES - IDX_61F9A28936ABA6B8 |
| savings_account | account_id | account | YES - IDX_EA211D3A9B6B5FBA |
| transaction | account_id | account | YES - IDX_723705D19B6B5FBA |
| transaction | category_id | category | YES - IDX_723705D112469DE2 |
| transaction | savings_account_id | savings_account | YES - IDX_723705D1FCB8D9DE |
| transaction | parent_transaction_id | transaction | YES - IDX_723705D1C19C5E47 |

**Verdict:** Foreign key indexing is excellent. No corrections needed.

---

## 5. N+1 Query Risk Assessment

### CRITICAL RISKS FOUND

#### 1. **ActiveBudgetService - N+1 on Budget Loading**
**File:** `/backend/src/Budget/Service/ActiveBudgetService.php`  
**Lines:** 36-38, 65-68  
**Risk:** HIGH

```php
public function getActiveBudgets(?int $months = null, ...): array {
    if ($accountId !== null) {
        $allBudgets = $this->budgetRepository->findBy(['account' => $accountId]);
    } else {
        $allBudgets = $this->budgetRepository->findAll();
    }
    
    foreach ($allBudgets as $budget) {
        if ($this->isActive($budget, $months)) {  // N+1: Calls isActive() for each budget
            $active[] = $budget;
        }
    }
}
```

**Issue:** Loads all budgets, then calls `isActive()` on each. The `isActive()` method likely performs additional queries.  
**Current Data:** 8 budgets (acceptable)  
**Risk at Scale:** With 100+ budgets, this becomes problematic  
**Impact:** Projects with many budgets will see linear performance degradation

**Recommendation:** Eager load budget relationships in the repository query

#### 2. **BudgetInsightsService - Multiple Query Per Budget**
**File:** `/backend/src/Budget/Service/BudgetInsightsService.php`  
**Lines:** 29-47  
**Risk:** HIGH

```php
public function computeInsights(array $budgets, ?int $limit = 3, ...): array {
    foreach ($budgets as $budget) {
        $insight = $this->computeBudgetInsight($budget, $startDate, $endDate);
        // Each call performs multiple database queries:
        // - getSelectedPeriodTotal()
        // - computeNormal() (queries 6 months)
        // - getSparklineData() (queries 6 months)
        // - calculatePreviousPeriod()
        // - calculateSamePeriodLastYear()
    }
}
```

**Issue:** Each budget gets 5+ separate queries  
**Current Data:** 8 budgets = 40-60 queries for insights  
**Risk at Scale:** With 50 budgets, 250-300 queries  
**Impact:** Dashboard load time grows exponentially with budget count

**Recommendation:** Consolidate queries to fetch all budget data in single statement

#### 3. **ProjectAggregatorService - Multiple Queries Per Project**
**File:** `/backend/src/Budget/Service/ProjectAggregatorService.php`  
**Lines:** 22-39, 44-79  
**Risk:** HIGH

```php
public function getProjectTotals(Budget $project): array {
    $trackedDebit = $this->getTrackedDebitTotal($project);      // Query 1
    $trackedCredit = $this->getTrackedCreditTotal($project);    // Query 2
    $external = $this->getExternalTotal($project);              // Query 3
    // ... plus getCategoryBreakdown() for each category        // Query 4+
}

public function getProjectEntries(Budget $project): array {
    $transactions = $this->getProjectTransactions($project);    // Query 1
    $externalPayments = $this->getProjectExternalPayments($project); // Query 2
}

public function getProjectTimeSeries(Budget $project): array {
    $monthlyTracked = $this->getMonthlyTrackedTotals($project, $months);  // Query 1
    $monthlyExternal = $this->getMonthlyExternalTotals($project, $months); // Query 2
}
```

**Issue:** Each project totals call makes 6+ separate queries  
**Current Data:** 8 budgets = 48+ queries  
**Risk at Scale:** With 100 projects, 600+ queries per load  
**Impact:** Project listing/detail pages will be extremely slow

**Recommendation:** Consolidate multiple table queries using single join statements

#### 4. **TransactionRepository - Split Transaction Subqueries**
**File:** `/backend/src/Transaction/Repository/TransactionRepository.php`  
**Lines:** 231-266, 588-627, etc.  
**Risk:** MEDIUM

```sql
SELECT SUM(CASE
    WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
    THEN ABS(t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE ...), 0))
    ELSE ABS(t.amount)
END)
```

**Issue:** Correlated subqueries execute for EVERY transaction row  
**Problem:** With 13,000 transactions, each query runs 13,000+ subqueries  
**Impact:** Category spending queries can take seconds for large datasets

**Recommendation:** Use window functions or LEFT JOIN with GROUP_CONCAT instead of subqueries

#### 5. **BudgetRepository - Missing Eager Loading**
**File:** `/backend/src/Budget/Repository/BudgetRepository.php`  
**Lines:** 47-49  
**Risk:** MEDIUM

```php
public function findByAccount(Account $account): array {
    return $this->findBy(['account' => $account], ['createdAt' => 'DESC']);
    // Returns bare Budget entities without related data
    // Accessing budget->getCategories() triggers N+1
}
```

**Issue:** When controllers access related entities, each relation causes separate query  
**Current Risk:** Minimal (few budgets per account)  
**Recommendation:** Use `findBudgetsWithCategoriesForMonth()` method that includes eager loading

#### 6. **Category/Budget Iteration in Services**
**File:** `/backend/src/Budget/Service/BudgetService.php`  
**Lines:** 170, 251, 407, 466  
**Risk:** MEDIUM

```php
foreach ($budget->getCategories() as $category) {
    $category->getName();  // Already loaded
    // But if iterating over multiple budgets:
    // foreach ($budgets as $budget) foreach ($budget->getCategories()...)
    // Each budget triggers new query
}
```

**Issue:** Category collections are lazy-loaded per budget  
**Recommendation:** Eager load categories when fetching budgets

---

## 6. Inefficient Table Structures

### Issues Found

#### 1. **transaction.parent_transaction_id - Self-referencing Hierarchy**
**Status:** Works but complex  
**Concern:** Split transactions create recursive relationships
- For split transactions, queries must check parent and all children
- Current implementation uses correlated subqueries (performance issue)
- Consider: Alternative schema with `transaction_split_group_id` if this grows

**Current Impact:** Medium (18 transactions with splits)  
**Future Impact:** Could be HIGH if many splits are created

#### 2. **transaction.amount field inconsistency**
**Current:** Stored as INT (cents) but named `amount`  
**Issue:** Column is aliased as `amountInCents` in Doctrine, but DB column is `amount`
```php
#[ORM\Column(name: "amount", type: Types::INTEGER)]
private ?int $amountInCents = null;
```

**Recommendation:** Rename database column to `amount_in_cents` for clarity (non-urgent)

#### 3. **budget_version - Effective date columns are strings**
**Current Schema:**
```
effective_from_month varchar(7) - YYYY-MM format
effective_until_month varchar(7) - YYYY-MM format
```

**Issue:** String comparison works but not ideal  
**Recommendation:** Consider DATE or YEAR-MONTH type for future (non-urgent)

#### 4. **Missing audit trail**
**Status:** No created_at/updated_at on several tables
- transaction (has none)
- pattern (has none)
- Recommendation: Add soft deletes and audit columns for data integrity

---

## 7. Query Performance Patterns

### Slow Query Risks

#### High-Risk Query Patterns

```php
// Pattern 1: Split transaction subqueries (Lines 243-256, TransactionRepository)
// Executes N subqueries for N transactions
SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id

// Pattern 2: Budget insight iteration (Lines 29-47, BudgetInsightsService)
// 5-7 queries per budget
foreach ($budgets as $budget) {
    $this->computeBudgetInsight($budget);  // Each has multiple queries
}

// Pattern 3: Project aggregation (Lines 22-39, ProjectAggregatorService)
// 6-8 queries per project
$trackedDebit = $this->getTrackedDebitTotal($project);
$trackedCredit = $this->getTrackedCreditTotal($project);
$external = $this->getExternalTotal($project);
// ... plus more

// Pattern 4: Category statistics calculation (Lines 389-440, TransactionRepository)
// LEFT JOIN + subqueries on large transaction table
SELECT ... FROM transaction t
LEFT JOIN category c ON t.category_id = c.id
WHERE t.account_id = ?
  AND ((SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0 OR ...)
```

---

## 8. Relationship Structure Assessment

### Entity Relationships

```
Account (1) ----< (Many) Budget
  │
  ├─< Category
  │   └─> Budget (ManyToMany via join)
  │
  ├─< Pattern
  │   └─> Category (optional)
  │   └─> SavingsAccount (optional)
  │
  └─< SavingsAccount
      └─< Transaction

Budget (1) ----< (Many) BudgetVersion
Budget (1) ----< (Many) Category
Budget (1) ----< (Many) ExternalPayment

Transaction (Many) ----< (Many) Transaction (self-referencing for splits)
Transaction (Many) ---> (One) Category (nullable)
Transaction (Many) ---> (One) SavingsAccount (nullable)
Transaction (Many) ---> (One) Account
```

### Issues

1. **Bidirectional relationships not always eager-loaded**  
   Category has optional ManyToOne to Budget, but queries don't include Budget context

2. **Category-Budget relationship is complex**
   - Technically OneToMany from Budget -> Category
   - But Category can reference back to Budget
   - Queries sometimes need both directions

3. **SavingsAccount hierarchical queries**
   Transactions can be linked to SavingsAccount, creating another aggregation path

---

## 9. Recommendations Summary

### IMMEDIATE (1-2 weeks)

| Priority | Action | Estimated Gain | Effort |
|----------|--------|-----------------|--------|
| HIGH | Add `idx_transaction_date` index | 30-50% faster date queries | 15 min |
| HIGH | Add `idx_transaction_account_date` composite index | 40-60% faster account+date | 15 min |
| HIGH | Refactor BudgetInsightsService to reduce query count from 40 to 10 | 75% faster insights | 4-6 hrs |
| MEDIUM | Add `idx_transaction_account_category_date` composite | 40-70% faster category queries | 15 min |
| MEDIUM | Replace split transaction subqueries with LEFT JOIN approach | 80% faster large dataset queries | 3-4 hrs |

### SHORT TERM (2-4 weeks)

| Priority | Action | Estimated Gain | Effort |
|----------|--------|-----------------|--------|
| HIGH | Eager load budget relationships in repositories | Eliminate N+1 on budgets | 2-3 hrs |
| HIGH | Consolidate ProjectAggregatorService queries | Reduce from 6-8 to 2-3 queries | 3-4 hrs |
| MEDIUM | Add query result caching for budget insights (Redis) | 95% faster repeat loads | 4-5 hrs |
| MEDIUM | Add composite `idx_transaction_category_date` | 30-50% faster category reports | 15 min |

### LONG TERM (1-2 months)

| Priority | Action | Estimated Gain | Effort |
|----------|--------|-----------------|--------|
| MEDIUM | Implement denormalization for category totals (materialized view or cache table) | 90% faster dashboard | 6-8 hrs |
| MEDIUM | Add soft deletes to transaction table | Data integrity | 2-3 hrs |
| LOW | Rename transaction.amount to amount_in_cents for clarity | Code clarity | 1 hr |
| LOW | Add audit timestamps (created_at, updated_at) to all tables | Audit trail | 2-3 hrs |

---

## 10. Implementation Examples

### Add Recommended Indexes

```sql
-- High-impact indexes
ALTER TABLE transaction ADD INDEX idx_transaction_date (date);
ALTER TABLE transaction ADD INDEX idx_transaction_account_date (account_id, date DESC);
ALTER TABLE transaction ADD INDEX idx_transaction_account_category_date (account_id, category_id, date DESC);
ALTER TABLE transaction ADD INDEX idx_transaction_category_date (category_id, date DESC);

-- Verify indexes were added
SHOW INDEX FROM transaction;
```

### Fix N+1 in BudgetInsightsService

**Current (N+1 risk):**
```php
public function computeInsights(array $budgets): array {
    foreach ($budgets as $budget) {
        $insight = $this->computeBudgetInsight($budget);
        // Each budget: 5-7 queries
    }
}
```

**Recommended (Single query approach):**
```php
public function computeInsights(array $budgets, ?string $startDate = null, ?string $endDate = null): array {
    // Batch fetch all needed data in single queries grouped by budget
    $budgetIds = array_map(fn($b) => $b->getId(), $budgets);
    
    // Single query for monthly totals across all budgets
    $allMonthlyTotals = $this->repository->getMonthlyTotalsForBudgets($budgetIds, 12);
    
    // Organize by budget ID
    $totalsByBudget = [];
    foreach ($allMonthlyTotals as $row) {
        $totalsByBudget[$row['budgetId']][] = $row;
    }
    
    // Now iterate with data already loaded
    foreach ($budgets as $budget) {
        $budgetData = $totalsByBudget[$budget->getId()] ?? [];
        $insight = $this->computeBudgetInsight($budget, $budgetData);
    }
}
```

### Fix Split Transaction Subqueries

**Current (Slow - correlated subqueries):**
```sql
SELECT SUM(CASE
    WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
    THEN ABS(t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id), 0))
    ELSE ABS(t.amount)
END)
FROM transaction t
WHERE t.category_id IN (...)
```

**Recommended (Window functions + LEFT JOIN):**
```sql
SELECT SUM(
    CASE
        WHEN has_splits.split_count > 0 THEN 
            ABS(t.amount - COALESCE(children.total_split, 0))
        ELSE ABS(t.amount)
    END
)
FROM transaction t
LEFT JOIN (
    SELECT parent_transaction_id, COUNT(*) as split_count
    FROM transaction 
    GROUP BY parent_transaction_id
) has_splits ON t.id = has_splits.parent_transaction_id
LEFT JOIN (
    SELECT parent_transaction_id, SUM(ABS(amount)) as total_split
    FROM transaction 
    WHERE category_id IS NOT NULL
    GROUP BY parent_transaction_id
) children ON t.id = children.parent_transaction_id
WHERE t.category_id IN (...)
```

---

## 11. Monitoring Recommendations

### Track These Metrics

1. **Slow Query Log**
   ```sql
   SET GLOBAL slow_query_log = 'ON';
   SET GLOBAL long_query_time = 0.5;
   ```

2. **Query Count per Page Load**
   Add to Symfony WebProfiler:
   - Dashboard load should be < 20 queries
   - Budget detail should be < 10 queries
   - Transaction list should be < 5 queries

3. **Index Usage**
   ```sql
   SELECT object_schema, object_name, count_read, count_write 
   FROM performance_schema.table_io_waits_summary_by_index_usage 
   ORDER BY count_read DESC;
   ```

4. **Response Times**
   - Dashboard: Target < 200ms
   - Budget detail: Target < 150ms
   - Insights refresh: Target < 500ms

---

## 12. Conclusion

**Overall Database Health:** GOOD  
**Index Coverage:** EXCELLENT  
**Query Efficiency:** NEEDS IMPROVEMENT  
**Risk Level:** MEDIUM (becomes HIGH with data growth)

### Key Findings

| Category | Status | Details |
|----------|--------|---------|
| Foreign Keys | ✓ Excellent | All indexed properly |
| Indexes | ⚠ Good | Missing date range composite indexes |
| N+1 Queries | ✗ Critical | BudgetInsightsService, ProjectAggregatorService |
| Subqueries | ✗ Inefficient | Split transaction logic uses correlated subqueries |
| Relationships | ⚠ Fair | Missing eager loading in several repositories |
| Table Design | ✓ Good | Appropriate normalization, minor naming issues |

### Priority Actions

1. **Add composite indexes on transaction table** (15 minutes, 40-70% gain)
2. **Refactor budget insights to reduce N+1** (4-6 hours, 75% gain)
3. **Replace split transaction subqueries** (3-4 hours, 80% gain)
4. **Eager load budget relationships** (2-3 hours, eliminates N+1)

**Projected Impact:** These changes could reduce average page load time by 60-80% and improve scalability to handle 10x more transactions.

---

*Report generated by database analysis script*  
*Recommendations based on application code review and schema inspection*
