# Budget Version Removal Plan

**Date**: 2025-11-07
**Goal**: Simplify budgets by removing BudgetVersion complexity and using a direct amount field on Budget entity
**Reason**: Budget versions were only used for testing and add unnecessary complexity to budget creation

---

## 🎯 Overview

Currently, budgets use a `BudgetVersion` system with:
- One-to-many relationship (Budget → BudgetVersion)
- Version effective dates (from/until months)
- Complex version history tracking
- Complicated UI for managing versions

**New simplified approach**:
- Budget has a direct `amount` field (Money type)
- No version history
- Simple budget creation: name + amount + type
- Clean UI without date range management

---

## 📊 Current State Analysis

### Database
- **9 budget versions** in database (testing data, no real meaning)
- `budget_version` table with columns: id, budget_id, monthly_amount, effective_from_month, effective_until_month, change_reason, created_at

### Backend Files to Remove/Modify
**Entities:**
- ❌ `src/Entity/BudgetVersion.php` - Delete entirely
- ✏️ `src/Entity/Budget.php` - Remove budgetVersions relationship, add amount field

**Controllers:**
- ❌ `src/Budget/Controller/BudgetVersionController.php` - Delete entirely
- ✏️ `src/Budget/Controller/BudgetController.php` - Simplify create/update endpoints

**Services:**
- ❌ `src/Budget/Service/BudgetVersionService.php` - Delete entirely
- ✏️ `src/Budget/Service/BudgetService.php` - Remove version-related methods

**Repositories:**
- ❌ `src/Budget/Repository/BudgetVersionRepository.php` - Delete entirely

**DTOs:**
- ❌ `src/Budget/DTO/BudgetVersionDTO.php` - Delete entirely
- ❌ `src/Budget/DTO/CreateBudgetVersionDTO.php` - Delete entirely
- ❌ `src/Budget/DTO/CreateSimpleBudgetVersionDTO.php` - Delete entirely
- ❌ `src/Budget/DTO/UpdateBudgetVersionDTO.php` - Delete entirely
- ✏️ `src/Budget/DTO/BudgetDTO.php` - Remove version fields, add amount field
- ✏️ `src/Budget/DTO/CreateBudgetDTO.php` - Simplify to just amount (no effectiveFromMonth)
- ✏️ `src/Budget/DTO/UpdateBudgetDTO.php` - Add amount field

**Mappers:**
- ✏️ `src/Budget/Mapper/BudgetMapper.php` - Use direct amount instead of getCurrentVersion()

### Frontend Files to Remove/Modify
**Models:**
- ✏️ `domains/budgets/models/Budget.ts` - Remove BudgetVersion interface and version fields

**Components:**
- ❌ `domains/budgets/components/BudgetVersionListItem.tsx` - Delete entirely
- ❌ `domains/budgets/components/AddBudgetVersionModal.tsx` - Delete entirely
- ✏️ `domains/budgets/components/BudgetCard.tsx` - Simplify to show direct amount
- ✏️ `domains/budgets/components/InlineBudgetEditor.tsx` - Simplify editing
- ✏️ `domains/budgets/components/CreateBudgetModal.tsx` - Remove date fields

**Services:**
- ✏️ `domains/budgets/services/BudgetsService.ts` - Remove version methods
- ✏️ `domains/budgets/services/BudgetsActions.ts` - Remove version actions

**Hooks:**
- ✏️ `domains/budgets/hooks/useBudgets.ts` - Remove version CRUD operations

**Pages:**
- ✏️ `domains/budgets/BudgetsPage.tsx` - Simplify budget management UI

---

## 🗺️ Step-by-Step Removal Plan

### Phase 1: Database Migration (Backend)

#### Step 1.1: Add amount field to budget table
**File**: `backend/migrations/VersionYYYYMMDDHHMMSS_AddAmountToBudget.php`

```php
public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE budget ADD COLUMN amount INT NOT NULL DEFAULT 0 COMMENT \'Monthly budget amount in cents\'');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE budget DROP COLUMN amount');
}
```

#### Step 1.2: Drop budget_version table
**File**: `backend/migrations/VersionYYYYMMDDHHMMSS_DropBudgetVersion.php`

```php
public function up(Schema $schema): void
{
    $this->addSql('DROP TABLE budget_version');
}

public function down(Schema $schema): void
{
    // Recreate table structure (for rollback safety)
    $this->addSql('CREATE TABLE budget_version (
        id INT AUTO_INCREMENT NOT NULL,
        budget_id INT NOT NULL,
        monthly_amount INT NOT NULL,
        effective_from_month VARCHAR(7) NOT NULL,
        effective_until_month VARCHAR(7) DEFAULT NULL,
        change_reason TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
        INDEX IDX_budget_id (budget_id),
        INDEX idx_effective_from (effective_from_month),
        INDEX idx_effective_until (effective_until_month),
        PRIMARY KEY(id)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

    $this->addSql('ALTER TABLE budget_version ADD CONSTRAINT FK_budget_id FOREIGN KEY (budget_id) REFERENCES budget (id) ON DELETE CASCADE');
}
```

---

### Phase 2: Backend Entity Changes

#### Step 2.1: Update Budget Entity
**File**: `backend/src/Entity/Budget.php`

**Remove:**
```php
#[ORM\OneToMany(targetEntity: BudgetVersion::class, mappedBy: 'budget', cascade: ['persist', 'remove'], orphanRemoval: true)]
#[ORM\OrderBy(['effectiveFromMonth' => 'DESC'])]
private Collection $budgetVersions;

// In constructor:
$this->budgetVersions = new ArrayCollection();

// Methods to remove:
public function getBudgetVersions(): Collection
public function addBudgetVersion(BudgetVersion $budgetVersion): static
public function removeBudgetVersion(BudgetVersion $budgetVersion): static
public function getEffectiveVersion(string $monthYear): ?BudgetVersion
public function getCurrentVersion(): ?BudgetVersion
```

**Add:**
```php
#[ORM\Column(name: "amount", type: Types::INTEGER)]
private int $amountInCents = 0;

public function getAmount(): Money
{
    return Money::EUR($this->amountInCents);
}

public function setAmount(Money $money): static
{
    $this->amountInCents = (int) $money->getAmount();
    return $this;
}
```

#### Step 2.2: Delete BudgetVersion Entity
**Action**: Delete file `backend/src/Entity/BudgetVersion.php`

---

### Phase 3: Backend DTOs and Mapper

#### Step 3.1: Update BudgetDTO
**File**: `backend/src/Budget/DTO/BudgetDTO.php`

**Remove:**
```php
public array $versions = [];
public ?float $currentMonthlyAmount = null;
public ?string $currentEffectiveFrom = null;
public ?string $currentEffectiveUntil = null;
```

**Add:**
```php
public ?float $amount = null;
```

#### Step 3.2: Update CreateBudgetDTO
**File**: `backend/src/Budget/DTO/CreateBudgetDTO.php`

**Change from:**
```php
public float $monthlyAmount;
public string $effectiveFromMonth;
public ?string $changeReason = null;
```

**To:**
```php
public float $amount;
```

#### Step 3.3: Update UpdateBudgetDTO
**File**: `backend/src/Budget/DTO/UpdateBudgetDTO.php`

**Add:**
```php
public ?float $amount = null;
```

#### Step 3.4: Delete BudgetVersion DTOs
**Actions**:
- Delete `backend/src/Budget/DTO/BudgetVersionDTO.php`
- Delete `backend/src/Budget/DTO/CreateBudgetVersionDTO.php`
- Delete `backend/src/Budget/DTO/CreateSimpleBudgetVersionDTO.php`
- Delete `backend/src/Budget/DTO/UpdateBudgetVersionDTO.php`

#### Step 3.5: Update BudgetMapper
**File**: `backend/src/Budget/Mapper/BudgetMapper.php`

**In `toDto()` method, replace:**
```php
// Map versions
$dto->versions = array_map(function (BudgetVersion $version) {
    return $this->budgetVersionToDto($version);
}, $budget->getBudgetVersions()->toArray());

// Current version convenience data
$currentVersion = $budget->getCurrentVersion();
if ($currentVersion) {
    $dto->currentMonthlyAmount = $currentVersion->getMonthlyAmount()->getAmount() / 100;
    $dto->currentEffectiveFrom = $currentVersion->getEffectiveFromMonth();
    $dto->currentEffectiveUntil = $currentVersion->getEffectiveUntilMonth();
}
```

**With:**
```php
// Direct amount
$dto->amount = $budget->getAmount()->getAmount() / 100;
```

**In `toSummaryDto()` method, replace:**
```php
$version = $budget->getEffectiveVersion($monthYear);
$allocatedAmount = $version ? $version->getMonthlyAmount()->getAmount() / 100 : 0;
```

**With:**
```php
$allocatedAmount = $budget->getAmount()->getAmount() / 100;
```

**Remove method:**
```php
private function budgetVersionToDto(BudgetVersion $version): BudgetVersionDTO
```

---

### Phase 4: Backend Services

#### Step 4.1: Update BudgetService
**File**: `backend/src/Budget/Service/BudgetService.php`

**In `create()` method, replace version creation:**
```php
// Create initial version
$initialVersion = new BudgetVersion();
$initialVersion->setBudget($budget);
$initialVersion->setMonthlyAmount($this->moneyFactory->create($data->monthlyAmount));
$initialVersion->setEffectiveFromMonth($data->effectiveFromMonth);
$initialVersion->setChangeReason($data->changeReason);
$budget->addBudgetVersion($initialVersion);
```

**With:**
```php
// Set amount directly
$budget->setAmount($this->moneyFactory->create($data->amount));
```

**In `update()` method, add amount update:**
```php
if (isset($data->amount)) {
    $budget->setAmount($this->moneyFactory->create($data->amount));
}
```

**In `getSummary()` method, replace:**
```php
$effectiveVersion = $budget->getEffectiveVersion($monthYear);
$allocatedMoney = $effectiveVersion ? $effectiveVersion->getMonthlyAmount() : Money::EUR(0);
$summary->allocatedAmount = $this->moneyFactory->toFloat($allocatedMoney);
```

**With:**
```php
$allocatedMoney = $budget->getAmount();
$summary->allocatedAmount = $this->moneyFactory->toFloat($allocatedMoney);
```

#### Step 4.2: Delete BudgetVersionService
**Action**: Delete `backend/src/Budget/Service/BudgetVersionService.php`

---

### Phase 5: Backend Controllers

#### Step 5.1: Update BudgetController
**File**: `backend/src/Budget/Controller/BudgetController.php`

**Simplify create endpoint** - already simplified (just uses CreateBudgetDTO)

**Update validation** - ensure amount is validated, not effectiveFromMonth

#### Step 5.2: Delete BudgetVersionController
**Action**: Delete `backend/src/Budget/Controller/BudgetVersionController.php`

---

### Phase 6: Backend Repository

#### Step 6.1: Delete BudgetVersionRepository
**Action**: Delete `backend/src/Budget/Repository/BudgetVersionRepository.php`

---

### Phase 7: Frontend Model Updates

#### Step 7.1: Update Budget Model
**File**: `frontend/src/domains/budgets/models/Budget.ts`

**Remove:**
```typescript
export interface BudgetVersion {
    id: number;
    monthlyAmount: number;
    effectiveFromMonth: string;
    effectiveUntilMonth: string | null;
    changeReason: string | null;
    createdAt: string;
    isCurrent: boolean;
    displayName: string;
}

export interface Budget {
    // ... other fields
    versions: BudgetVersion[];
    currentMonthlyAmount: number | null;
    currentEffectiveFrom: string | null;
    currentEffectiveUntil: string | null;
}

export interface CreateBudgetVersion { ... }
export interface UpdateBudgetVersion { ... }
export interface DateOverlapValidation { ... }
export interface VersionChangePreview { ... }
export interface VersionAction { ... }
export interface UpdateVsAddDecision { ... }
export interface VersionUpdatePreview { ... }
```

**Update:**
```typescript
export interface Budget {
    id: number;
    name: string;
    accountId: number;
    budgetType: BudgetType;
    icon?: string | null;
    status: 'ACTIVE' | 'INACTIVE' | 'ARCHIVED';
    statusLabel: string;
    statusColor: string;
    createdAt: string;
    updatedAt: string;
    categories: Category[];
    amount: number;  // Direct amount field
}

export interface CreateBudget {
    name: string;
    accountId: number;
    budgetType: BudgetType;
    icon?: string | null;
    amount: number;  // Simplified - just amount
    categoryIds?: number[];
}

export interface UpdateBudget {
    name?: string;
    budgetType?: BudgetType;
    icon?: string | null;
    status?: 'ACTIVE' | 'INACTIVE' | 'ARCHIVED';
    amount?: number;  // Add amount to update
}
```

---

### Phase 8: Frontend Services

#### Step 8.1: Update BudgetsService
**File**: `frontend/src/domains/budgets/services/BudgetsService.ts`

**Remove functions:**
- `createBudgetVersion()`
- `updateBudgetVersion()`
- `deleteBudgetVersion()`
- Any version-related helper functions

**Keep:**
- `createBudget()` - simplify to not require effectiveFromMonth
- `updateBudget()` - add amount parameter
- `deleteBudget()`
- `getBudgets()`
- `getBudget()`

#### Step 8.2: Update BudgetsActions
**File**: `frontend/src/domains/budgets/services/BudgetsActions.ts`

**Remove:**
- All version-related action functions

---

### Phase 9: Frontend Components

#### Step 9.1: Delete Version Components
**Actions:**
- Delete `frontend/src/domains/budgets/components/BudgetVersionListItem.tsx`
- Delete `frontend/src/domains/budgets/components/AddBudgetVersionModal.tsx`

#### Step 9.2: Update CreateBudgetModal
**File**: `frontend/src/domains/budgets/components/CreateBudgetModal.tsx`

**Remove:**
- `effectiveFromMonth` date input field
- `changeReason` text field

**Keep:**
- Name input
- Budget type selector
- Amount input (simplified, no date requirement)
- Category assignment

#### Step 9.3: Update BudgetCard
**File**: `frontend/src/domains/budgets/components/BudgetCard.tsx`

**Replace version display with direct amount:**
```typescript
// Before: showing current version
<div className="text-sm text-gray-600">
    {budget.currentMonthlyAmount
        ? `€${budget.currentMonthlyAmount.toFixed(2)} (vanaf ${budget.currentEffectiveFrom})`
        : 'Geen actieve versie'}
</div>

// After: showing direct amount
<div className="text-sm text-gray-600">
    €{budget.amount.toFixed(2)} / maand
</div>
```

**Remove:**
- Version history section
- "Add Version" button
- Version list rendering

#### Step 9.4: Update InlineBudgetEditor
**File**: `frontend/src/domains/budgets/components/InlineBudgetEditor.tsx`

**Simplify to edit:**
- Name
- Amount (direct field, no dates)
- Budget type
- Icon

**Remove:**
- Version management UI
- Date range inputs

#### Step 9.5: Update BudgetsPage
**File**: `frontend/src/domains/budgets/BudgetsPage.tsx`

**Remove:**
- `createBudgetVersion` from useBudgets
- `updateBudgetVersion` from useBudgets
- `deleteBudgetVersion` from useBudgets
- Any version-related state or handlers

**Keep:**
- Budget CRUD operations
- Category assignment
- Statistics display

---

### Phase 10: Frontend Hooks

#### Step 10.1: Update useBudgets
**File**: `frontend/src/domains/budgets/hooks/useBudgets.ts`

**Remove:**
- `createBudgetVersion` function
- `updateBudgetVersion` function
- `deleteBudgetVersion` function

**Return object should only have:**
```typescript
return {
    budgets,
    availableCategories,
    isLoading,
    error,
    createBudget,
    updateBudget,
    deleteBudget,
    assignCategories,
    removeCategory,
    refresh
};
```

---

## 🧪 Testing Plan

### Backend Tests
1. **Create Budget**
   - POST `/api/budgets` with `{ name, budgetType, amount, accountId }`
   - Verify budget is created with direct amount
   - Verify no budget_version records are created

2. **Update Budget**
   - PATCH `/api/budgets/{id}` with `{ amount }`
   - Verify budget amount is updated

3. **Get Budget**
   - GET `/api/budgets/{id}`
   - Verify response includes `amount` field
   - Verify response does NOT include `versions` or `currentMonthlyAmount`

4. **Budget Summary**
   - GET `/api/budgets/{id}/summary?monthYear=2025-11`
   - Verify allocated amount uses direct budget amount

### Frontend Tests
1. **Budget Creation**
   - Open "Create Budget" modal
   - Fill in: Name, Type, Amount
   - Verify: No date field required
   - Submit and verify budget is created

2. **Budget Display**
   - View budget card
   - Verify: Shows direct amount (€X.XX / maand)
   - Verify: No version history shown

3. **Budget Editing**
   - Click edit on budget
   - Change amount
   - Save and verify amount updates

4. **Budget Deletion**
   - Delete budget
   - Verify: Budget and associated data removed

### Database Verification
```sql
-- After migration, verify structure
DESCRIBE budget;
-- Should show 'amount' column

-- Verify no orphaned data
SELECT COUNT(*) FROM budget_version;
-- Should error (table doesn't exist) after final migration
```

---

## 🚀 Deployment Steps

1. **Run migrations**:
   ```bash
   docker exec money-backend php bin/console doctrine:migrations:migrate
   ```

2. **Rebuild frontend**:
   ```bash
   docker compose build frontend && docker compose up -d frontend
   ```

3. **Verify**:
   - Create new budget (should be simple, no dates)
   - Edit existing budget
   - Check database structure

---

## 📝 Files Summary

### Backend - Delete (8 files):
1. ❌ `src/Entity/BudgetVersion.php`
2. ❌ `src/Budget/Controller/BudgetVersionController.php`
3. ❌ `src/Budget/Service/BudgetVersionService.php`
4. ❌ `src/Budget/Repository/BudgetVersionRepository.php`
5. ❌ `src/Budget/DTO/BudgetVersionDTO.php`
6. ❌ `src/Budget/DTO/CreateBudgetVersionDTO.php`
7. ❌ `src/Budget/DTO/CreateSimpleBudgetVersionDTO.php`
8. ❌ `src/Budget/DTO/UpdateBudgetVersionDTO.php`

### Backend - Modify (6 files):
1. ✏️ `src/Entity/Budget.php`
2. ✏️ `src/Budget/Controller/BudgetController.php`
3. ✏️ `src/Budget/Service/BudgetService.php`
4. ✏️ `src/Budget/Mapper/BudgetMapper.php`
5. ✏️ `src/Budget/DTO/BudgetDTO.php`
6. ✏️ `src/Budget/DTO/CreateBudgetDTO.php`

### Backend - Create (2 migrations):
1. ➕ Migration: Add amount to budget table
2. ➕ Migration: Drop budget_version table

### Frontend - Delete (2 files):
1. ❌ `domains/budgets/components/BudgetVersionListItem.tsx`
2. ❌ `domains/budgets/components/AddBudgetVersionModal.tsx`

### Frontend - Modify (9 files):
1. ✏️ `domains/budgets/models/Budget.ts`
2. ✏️ `domains/budgets/services/BudgetsService.ts`
3. ✏️ `domains/budgets/services/BudgetsActions.ts`
4. ✏️ `domains/budgets/hooks/useBudgets.ts`
5. ✏️ `domains/budgets/components/BudgetCard.tsx`
6. ✏️ `domains/budgets/components/InlineBudgetEditor.tsx`
7. ✏️ `domains/budgets/components/CreateBudgetModal.tsx`
8. ✏️ `domains/budgets/BudgetsPage.tsx`

---

## ⚠️ Important Notes

1. **Adaptive Dashboard**: Already doesn't use versions - will be unaffected ✅
2. **PROJECT Budgets**: Use `startDate`/`endDate` instead of versions - will be unaffected ✅
3. **Data Loss**: All budget version history will be deleted (acceptable since it's test data)
4. **Breaking Change**: Old budget API responses will change (no versions field)
5. **Simpler UX**: Budget creation becomes much simpler for users 🎉

---

**Plan Created**: 2025-11-07
**Status**: ✅ READY FOR EXECUTION
**Estimated Effort**: 2-3 hours
**Risk Level**: Medium (many files to change, but straightforward refactor)
