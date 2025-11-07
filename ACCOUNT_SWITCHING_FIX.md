# Account Switching Fix

**Date**: 2025-11-07
**Issue**: Account dropdown doesn't allow switching to non-default accounts - it reverts back to the default account
**Root Cause**: `refreshAccounts()` was always resetting to the default account, overriding manual user selections
**Fix**: Modified AccountContext to only auto-select accounts on initial load, not after user selections

---

## 🐛 The Problem

When a user has multiple accounts and tries to switch from the default account to another account using the dropdown:
1. Dropdown selection changes
2. Page briefly shows the selected account (e.g., empty transactions)
3. Page refreshes and reverts back to the default account's data

**User Impact**:
- Users with multiple accounts cannot access non-default accounts
- Data from secondary accounts is inaccessible via the UI
- Frustrating user experience

---

## 🔍 Root Cause Analysis

### The Flow That Was Breaking Account Switching:

1. **User selects account 2** from dropdown
2. `setAccountId(2)` is called in AccountContext
3. `accountId` state changes from 1 → 2
4. `refreshAccounts` callback is recreated (because it depends on `accountId`)
5. `App.tsx` has a useEffect that depends on `refreshAccounts`:
   ```typescript
   useEffect(() => {
       if (isAuthenticated && !authLoading) {
           refreshAccounts();
       }
   }, [isAuthenticated, authLoading, refreshAccounts]);
   ```
6. Because `refreshAccounts` changed, the useEffect runs again
7. `refreshAccounts()` is called
8. Inside `refreshAccounts`, this code **always** reset to default account:
   ```typescript
   const defaultAccount = data.find(a => a.isDefault);
   if (defaultAccount) {
       setAccountId(defaultAccount.id); // ❌ ALWAYS overrides user selection!
   }
   ```
9. Account is forced back to account 1 (the default)

### Why This Logic Was Wrong:

The original logic assumed the default account should **always** be selected, regardless of user actions. This made sense for:
- Initial page load ✅
- After logout/login ✅

But it broke:
- Manual account switching ❌
- User preference persistence ❌

---

## ✅ The Solution

Modified `AccountContext.tsx` to respect user account selections:

### New Logic:

```typescript
const refreshAccounts = useCallback(async () => {
    setIsLoading(true);
    try {
        const res = await api.get('/accounts');
        const data: Account[] = res.data;
        setAccounts(data);

        if (data.length > 0) {
            // Only auto-select an account if none is currently selected
            if (accountId === null) {
                // INITIAL LOAD ONLY
                const defaultAccount = data.find(a => a.isDefault);

                if (defaultAccount) {
                    setAccountId(defaultAccount.id);
                } else {
                    // No default - use stored or first account
                    const storedId = sessionStorage.getItem('accountId');
                    const storedAccountId = storedId ? Number(storedId) : null;
                    const storedAccount = storedAccountId ? data.find(a => a.id === storedAccountId) : null;

                    const sortedAccounts = [...data].sort((a, b) => a.id - b.id);
                    const newAccountId = storedAccount?.id ?? sortedAccounts[0]?.id ?? null;
                    setAccountId(newAccountId);
                }
            } else {
                // ACCOUNT ALREADY SELECTED - verify it still exists
                const currentAccountExists = data.find(a => a.id === accountId);

                if (!currentAccountExists) {
                    // Current account was deleted - fall back to default or first
                    const defaultAccount = data.find(a => a.isDefault);
                    const sortedAccounts = [...data].sort((a, b) => a.id - b.id);
                    const newAccountId = defaultAccount?.id ?? sortedAccounts[0]?.id ?? null;
                    setAccountId(newAccountId);
                }
                // Otherwise: Keep current selection! ✅
            }
        }
    } catch (error) {
        console.error('Error loading accounts:', error);
        setAccounts([]);
    } finally {
        setIsLoading(false);
    }
}, [accountId]);
```

### Key Changes:

1. **Check if accountId is null** (`if (accountId === null)`)
   - If null: Auto-select default account (initial load behavior)
   - If not null: Keep current selection unless account was deleted

2. **Verify account still exists**
   - If user has account 2 selected but account 2 is deleted
   - Only then fall back to default account

3. **Respect user selection**
   - If user manually switches to account 2
   - `refreshAccounts()` will no longer override it

---

## 🔄 New Flow

### Initial Load (accountId === null):
```
1. User logs in
   ↓
2. App.tsx calls refreshAccounts()
   ↓
3. AccountContext: accountId is null
   ↓
4. Find default account
   ↓
5. setAccountId(defaultAccount.id)
   ✅ User sees default account
```

### Manual Account Switch:
```
1. User selects account 2 from dropdown
   ↓
2. setAccountId(2)
   ↓
3. accountId changes: 1 → 2
   ↓
4. refreshAccounts callback recreated
   ↓
5. App.tsx useEffect triggers refreshAccounts()
   ↓
6. AccountContext: accountId is 2 (not null!)
   ↓
7. Verify account 2 still exists: ✅ Yes
   ↓
8. Keep accountId = 2
   ✅ User stays on account 2
```

### Account Deleted (Edge Case):
```
1. User has account 2 selected
   ↓
2. Admin deletes account 2 via backend
   ↓
3. refreshAccounts() is called
   ↓
4. AccountContext: accountId is 2
   ↓
5. Verify account 2 still exists: ❌ No
   ↓
6. Fall back to default account
   ✅ User sees default account (safe fallback)
```

---

## 🎯 Benefits

1. **Account switching works** - Users can access all their accounts
2. **Respects user choice** - Selected account persists across refreshes
3. **Session persistence** - accountId saved to sessionStorage
4. **Safe fallbacks** - Handles deleted accounts gracefully
5. **Initial load behavior unchanged** - Default account still selected on login

---

## 🧪 Testing

### Test Cases:

#### 1. Initial Load
**Steps**:
1. Clear browser storage: `localStorage.clear(); sessionStorage.clear();`
2. Login to application
3. **Expected**: Default account (isDefault=true) is selected

#### 2. Manual Account Switch
**Steps**:
1. Login with multiple accounts
2. Note current account (should be default)
3. Select different account from dropdown
4. Navigate to Transactions page
5. **Expected**: Transactions for selected account are shown
6. Navigate to other pages (Budgets, Categories)
7. **Expected**: Data for selected account persists

#### 3. Page Refresh with Manual Selection
**Steps**:
1. Select non-default account
2. Refresh page (F5)
3. **Expected**: Same account remains selected (via sessionStorage)

#### 4. Account Persistence Across Navigation
**Steps**:
1. Select account 2
2. Navigate to Transactions → see account 2 data
3. Navigate to Budgets → see account 2 budgets
4. Navigate to Categories → see account 2 categories
5. **Expected**: Account 2 remains selected throughout

#### 5. Deleted Account Fallback
**Steps**:
1. Select account 2
2. Delete account 2 from database
3. Call refreshAccounts()
4. **Expected**: Falls back to default account or first available

---

## 📝 Files Changed

### Frontend (1 file):
1. ✅ `frontend/src/app/context/AccountContext.tsx` - Modified `refreshAccounts()` logic

**Changes**:
- Added `if (accountId === null)` check for initial load
- Auto-select default account only when no account is selected
- Verify current account still exists before keeping selection
- Fall back to default only if current account was deleted

---

## 🔒 Related Context Features

### SessionStorage Persistence
Account selection is saved to `sessionStorage`:
```typescript
useEffect(() => {
    if (accountId !== null) {
        sessionStorage.setItem('accountId', String(accountId));
    } else {
        sessionStorage.removeItem('accountId');
    }
}, [accountId]);
```

### Account Selector UI
Located in `frontend/src/shared/components/AccountSelector/AccountSelector.tsx`:
- Shows all accounts sorted (default first, then alphabetically)
- Displays account name + account number
- Calls `onAccountChange(accountId)` when user selects different account

### Multi-Account Security
Backend verifies account ownership via `verifyAccountOwnership()`:
```php
private function verifyAccountOwnership(int $accountId): ?JsonResponse
{
    $user = $this->getUser();
    if (!$user) return 401;

    $account = $this->accountRepository->find($accountId);
    if (!$account->isOwnedBy($user)) return 403;

    return null;
}
```

---

## 🚀 Deployment

**Steps**:
1. Pull latest code from repository
2. Rebuild frontend: `docker compose build frontend`
3. Restart frontend: `docker compose up -d frontend`
4. Verify: Test account switching with multiple accounts

**No backend changes required** - this is purely a frontend state management fix.

---

**Fix Applied**: 2025-11-07
**Status**: ✅ COMPLETE
**Breaking Changes**: None
**User Experience**: Significantly improved - account switching now works as expected
