# Split Transactions & Pattern Auto-Categorization - 2025-11-05

## Summary

This document summarizes the implementation of automatic pattern application to split transactions and the subsequent fixes required for budget calculations.

## Features Implemented

### 1. Pattern Auto-Categorization for Split Transactions

Split transactions now automatically apply existing categorization patterns when created, enabling automatic categorization of recurring purchases (subscriptions, specific merchants) based on pattern matching rules.

#### Implementation Details

**Backend Changes:**
- **File:** `backend/src/Transaction/Service/TransactionSplitService.php`
- **Dependencies Added:**
  - `PatternRepository` - to fetch account patterns
  - `PatternAssignService` - to apply pattern matching logic

**New Method:**
```php
private function applyPatternsToSplits(Transaction $parentTransaction): void
{
    $account = $parentTransaction->getAccount();
    $patterns = $this->patternRepository->findByAccountId($account->getId());

    // Apply each pattern - they will only affect matching uncategorized splits
    foreach ($patterns as $pattern) {
        $this->patternAssignService->assignSinglePattern($pattern);
    }
}
```

**Integration Points:**
1. `createSplitsFromParsedData()` - Called after PDF import creates splits
2. `createSplit()` - Called after manual split creation

**How It Works:**
When split transactions are created (either via PDF import or manually):
1. Splits are created and saved to database
2. All patterns for the account are fetched
3. Each pattern is applied to the newly created splits
4. Matching splits are automatically categorized
5. Non-matching splits remain uncategorized for manual assignment

**Example:**
If you have a pattern:
- Description contains "Netflix" → Category: "Subscriptions"

When importing a credit card statement with a Netflix charge, that split will be automatically categorized as "Subscriptions" immediately after creation.

## Bug Fixes

### 2. DQL Subquery Syntax Errors

**Problem:**
Doctrine Query Language (DQL) doesn't support correlated subqueries in SELECT clauses, causing syntax errors:
```
[Syntax Error] line 0, col 307: Error: Expected Literal, got 'SELECT'
```

**Root Cause:**
The adjusted parent amount logic (implemented previously) used complex CASE statements with subqueries in the SELECT clause to calculate adjusted transaction amounts. This pattern worked in native SQL but isn't supported in DQL.

**Solution:**
Converted all affected queries from DQL (QueryBuilder) to native SQL using the Doctrine Connection API.

#### Files Modified

**1. BudgetInsightsService.php (2 methods)**
- `getMonthlyTotals()` - Monthly budget totals for sparkline data
- `getDateRangeTotal()` - Date range calculations for insights

**2. ProjectAggregatorService.php (4 methods)**
- `getTrackedTotal()` - Total from tracked transactions
- `getCategoryBreakdown()` - Category breakdown for projects
- `getProjectTransactions()` - All project transactions (hybrid approach: SQL for IDs, repository for entities)
- `getMonthlyTrackedTotals()` - Monthly time series data

**3. TransactionRepository.php (8 methods)**
- `getTotalSpentByCategoriesInMonth()`
- `getCategoryBreakdownForMonth()`
- `getCategoryBreakdownForDateRange()`
- `getMonthlySpentByCategories()`
- `getCategoryStatistics()`
- `getCurrentMonthTotalByCategory()`
- `getTotalSpentByCategoriesInPeriod()`
- `getMonthlyTotalsByCategory()`

#### Conversion Pattern

**Before (DQL with QueryBuilder):**
```php
$qb = $this->entityManager->createQueryBuilder();
$qb->select("
    SUM(
        CASE
            WHEN (SELECT COUNT(st.id) FROM App\Entity\Transaction st WHERE st.parentTransaction = t) > 0
            THEN (t.amountInCents - COALESCE(...))
            ELSE t.amountInCents
        END
    ) as total
")
->from('App\Entity\Transaction', 't')
->where('t.category IN (:categoryIds)')
->setParameter('categoryIds', $categoryIds);

$result = $qb->getQuery()->getSingleScalarResult();
```

**After (Native SQL):**
```php
$categoryPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));

$sql = "
    SELECT
        SUM(
            CASE
                WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
                THEN (t.amount - COALESCE(...))
                ELSE t.amount
            END
        ) as total
    FROM transaction t
    WHERE t.category_id IN ($categoryPlaceholders)
";

$result = $this->entityManager->getConnection()->executeQuery($sql, $categoryIds)->fetchOne();
```

**Key Differences:**
- Table names: `App\Entity\Transaction` → `transaction`
- Column names: `amountInCents` → `amount`, `parentTransaction` → `parent_transaction_id`
- Parameter binding: Named parameters → positional parameters with placeholders
- Execution: `getQuery()` → `getConnection()->executeQuery()`
- Result fetching: `getSingleScalarResult()` → `fetchOne()` or `fetchAllAssociative()`

### 3. Database Column Name Errors

**Problem:**
After converting to native SQL, queries failed with:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 't.amount_in_cents' in 'field list'
```

**Root Cause:**
Incorrect assumption about Doctrine's column naming strategy. The entity property `amountInCents` doesn't automatically become `amount_in_cents` in the database.

**Actual Database Schema:**
```sql
DESCRIBE transaction;
-- Column name is 'amount', not 'amount_in_cents'
```

**Solution:**
Global find-and-replace across all native SQL queries:
- `amount_in_cents` → `amount`

**Correct Column Names:**
- ✅ `amount` (not `amount_in_cents`)
- ✅ `parent_transaction_id`
- ✅ `category_id`
- ✅ `transaction_type`
- ✅ `date`

## Technical Details

### Adjusted Parent Amount Logic (Preserved)

The native SQL conversion preserved the critical adjusted parent amount logic:

**Business Rules:**
1. **No categorized splits:** Parent shows full amount in budget
2. **Some categorized splits:** Parent shows adjusted amount (original - categorized children)
3. **All categorized splits:** Parent hidden from budget (adjusted amount = €0)

**SQL Implementation:**
```sql
SUM(
    CASE
        -- If parent has splits
        WHEN (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) > 0
        THEN
            -- Calculate adjusted amount: parent - sum of categorized children
            CASE WHEN t.transaction_type = 'CREDIT'
            THEN -(t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0))
            ELSE (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0))
            END
        -- Regular transaction: use full amount
        ELSE
            CASE WHEN t.transaction_type = 'CREDIT' THEN -t.amount ELSE t.amount END
    END
) as total
```

**Exclusion Logic:**
```sql
-- Exclude parent only if adjusted amount is zero (all children categorized)
WHERE (
    (SELECT COUNT(st.id) FROM transaction st WHERE st.parent_transaction_id = t.id) = 0
    OR
    (t.amount - COALESCE((SELECT SUM(ABS(st.amount)) FROM transaction st WHERE st.parent_transaction_id = t.id AND st.category_id IS NOT NULL), 0)) != 0
)
```

### Performance Considerations

**Subquery Implications:**
Each main query now executes multiple correlated subqueries:
- COUNT subquery to detect splits (executed per row)
- SUM subquery to calculate categorized children amount (executed per parent)

**Potential Optimizations (Future):**
- Add database indexes on `parent_transaction_id` (already exists)
- Consider materialized view or cached calculation for frequently accessed budgets
- Batch pattern application instead of per-pattern execution

### Testing Recommendations

**Manual Testing Checklist:**
- ✅ Create split transactions via PDF import
- ✅ Verify patterns auto-apply to matching splits
- ✅ Categorize some splits, verify parent shows adjusted amount in budget
- ✅ Categorize all splits, verify parent hidden from budget
- ✅ Check dashboard insights render correctly
- ✅ Verify project budget calculations work
- ✅ Test monthly/yearly budget breakdowns

**Edge Cases to Test:**
- Parent with no splits (should work as before)
- Parent with all uncategorized splits (should show full amount)
- Parent with mixed categorized/uncategorized splits (should show adjusted)
- Pattern matching with special characters in descriptions
- Multiple patterns matching same split (first match wins)

## Commits

1. **Pattern Auto-Categorization:**
   - Commit: `019f538` - feat: Auto-apply patterns to split transactions

2. **DQL to Native SQL Conversion:**
   - Commit: `a9e4274` - fix: Convert DQL subqueries to native SQL for adjusted parent amount calculations

3. **Column Name Corrections:**
   - Commit: `b4bcae3` - fix: Use correct database column names in native SQL queries

## Migration Notes

**No Database Migrations Required:**
- All changes are code-level only
- Existing data remains unchanged
- No new tables or columns added

**Backward Compatibility:**
- Pattern application is additive (doesn't break existing splits)
- Budget calculations use same business logic (just different SQL syntax)
- No API contract changes

## Known Limitations

1. **Pattern Application Order:**
   - Patterns are applied sequentially in database order
   - First matching pattern wins (no priority system)
   - Manually categorized splits are not re-categorized

2. **Performance:**
   - Budget calculations with many splits may be slower due to subqueries
   - No caching layer for frequently accessed calculations

3. **SQL Portability:**
   - Native SQL queries are MySQL-specific
   - Migration to PostgreSQL would require query adjustments

## Future Improvements

1. **Pattern Priority System:**
   - Add priority field to patterns
   - Apply higher priority patterns first
   - Allow multiple patterns to suggest categories (user chooses)

2. **Performance Optimization:**
   - Add computed column for adjusted parent amounts
   - Implement query result caching
   - Add database indexes for common query patterns

3. **Testing:**
   - Add unit tests for pattern application
   - Add integration tests for budget calculation queries
   - Add performance benchmarks for large datasets

4. **Monitoring:**
   - Add logging for pattern application results
   - Track query execution times
   - Alert on slow budget calculations

## References

- **Original Issue:** Credit card transaction splitting functionality
- **Related Features:**
  - Transaction splitting (implemented previously)
  - Pattern matching system (existing)
  - Adjusted parent amount logic (implemented previously)
- **Doctrine Documentation:** [Native SQL](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/native-sql.html)
- **MySQL Documentation:** [Subqueries](https://dev.mysql.com/doc/refman/8.0/en/subqueries.html)
