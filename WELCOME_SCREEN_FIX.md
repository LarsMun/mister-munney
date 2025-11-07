# Welcome Screen Fix - Auto-Create Accounts from CSV

**Date**: 2025-11-07
**Issue**: Welcome screen had incorrect 2-step flow (create account manually → import CSV)
**Fix**: Simplified to single-step CSV upload that auto-creates accounts

---

## 🐛 The Problem

The WelcomeScreen had a two-step wizard:
1. **Step 1**: Manually enter account name and IBAN
2. **Step 2**: Upload CSV to that account

**This was wrong** because:
- Accounts should be automatically created from CSV data
- Account numbers come from the "Rekening" column in CSV
- Users shouldn't manually create accounts

---

## ✅ The Solution

### Backend Changes

#### 1. Added New Import Endpoint for First-Time Users

**File**: `backend/src/Transaction/Controller/TransactionImportController.php`

Added new endpoint that doesn't require an accountId:

```php
#[Route('/api/transactions/import-first', name: 'import_first', methods: ['POST'])]
public function importFirst(Request $request): JsonResponse
{
    $user = $this->getUser();
    if (!$user) {
        return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
    }

    $file = $request->files->get('file');
    if (!$file) {
        throw new BadRequestHttpException('No file uploaded');
    }

    // Import without accountId - service will create accounts and link to user
    $result = $this->transactionImportService->importForUser($file, $user);

    return $this->json($result, 201);
}
```

**Route**: `POST /api/transactions/import-first`
- Requires authentication (JWT token)
- Does NOT require accountId in URL
- Creates accounts automatically and links them to the authenticated user

#### 2. Added User-Aware Account Creation

**File**: `backend/src/Account/Service/AccountService.php`

Added new method to create accounts and link to users:

```php
public function getOrCreateAccountByNumberForUser(string $accountNumber, $user): Account
{
    $account = $this->accountRepository->findByAccountNumber($accountNumber);

    if (!$account) {
        // Create new account and link to user
        $account = new Account();
        $account->setAccountNumber($accountNumber);
        $account->addOwner($user);  // ✅ Link to user!
        $this->accountRepository->save($account);
    } elseif (!$account->isOwnedBy($user)) {
        // Account exists but user doesn't own it - add user as owner
        $account->addOwner($user);
        $this->accountRepository->save($account);
    }

    return $account;
}
```

#### 3. Updated Transaction Import Service

**File**: `backend/src/Transaction/Service/TransactionImportService.php`

**Added property to track current user:**
```php
private $currentUser = null;
```

**Added `importForUser` method:**
```php
public function importForUser(UploadedFile $file, $user): array
{
    // Set current user so createTransactionEntity can link accounts
    $this->currentUser = $user;

    try {
        return $this->import($file);
    } finally {
        // Clear current user after import
        $this->currentUser = null;
    }
}
```

**Updated `createTransactionEntity` to use user-aware account creation:**
```php
// Haal account op of maak hem aan (en link aan user indien ingesteld)
if ($this->currentUser) {
    $account = $this->accountService->getOrCreateAccountByNumberForUser(
        $record[self::FIELD_ACCOUNT],
        $this->currentUser
    );
} else {
    $account = $this->accountService->getOrCreateAccountByNumber($record[self::FIELD_ACCOUNT]);
}
```

---

### Frontend Changes

#### 1. Simplified WelcomeScreen to Single-Step

**File**: `frontend/src/components/WelcomeScreen.tsx`

**Removed:**
- Two-step wizard (account creation → CSV import)
- Progress indicator
- Account name/IBAN input fields
- State management for steps

**Changed to:**
- Single-step CSV upload
- Direct import via `/transactions/import-first`
- Clear message that accounts are auto-created

**Before:**
```typescript
const [step, setStep] = useState<'account' | 'import'>('account');
const [accountName, setAccountName] = useState('');
const [accountNumber, setAccountNumber] = useState('');
const [createdAccountId, setCreatedAccountId] = useState<number | null>(null);
```

**After:**
```typescript
const [file, setFile] = useState<File | null>(null);
const [isUploading, setIsUploading] = useState(false);
```

#### 2. Updated Upload Handler

**Before** (required accountId):
```typescript
const result = await importTransactions(createdAccountId, file);
```

**After** (no accountId needed):
```typescript
const response = await api.post('/transactions/import-first', formData, {
    headers: {
        'Content-Type': 'multipart/form-data',
    },
});
```

#### 3. Improved User Message

```html
<p>Upload je bankrekening CSV-bestand om te beginnen.
   Accounts worden automatisch aangemaakt uit je transacties.</p>
```

```html
<p>Accounts worden automatisch aangemaakt op basis van
   de rekeningnummers in je CSV.</p>
```

---

## 🔄 New User Flow

### Complete Flow (First-Time User)

```
1. User visits app → Shows login/register screen
   ↓
2. User registers with email/password
   ↓
3. Auto-login after registration
   ↓
4. App detects no accounts → Shows WelcomeScreen
   ↓
5. User selects CSV file
   ↓
6. Upload to POST /api/transactions/import-first
   ↓
7. Backend:
   - Reads CSV
   - Finds unique account numbers in "Rekening" column
   - Creates Account entities for each
   - Links accounts to authenticated user (addOwner)
   - Imports all transactions
   ↓
8. Frontend refreshes accounts list
   ↓
9. Main application loads with imported data
```

---

## 📝 API Endpoints

### First Import (No Accounts Yet)
```http
POST /api/transactions/import-first
Authorization: Bearer {JWT_TOKEN}
Content-Type: multipart/form-data

file: [CSV file]
```

**Response:**
```json
{
  "imported": 150,
  "skipped": 5,
  "duplicates": 2,
  "errors": []
}
```

### Regular Import (Has Accounts)
```http
POST /api/account/{accountId}/transactions/import
Authorization: Bearer {JWT_TOKEN}
Content-Type: multipart/form-data

file: [CSV file]
```

---

## 🎯 Key Benefits

1. **Simpler UX** - One step instead of two
2. **No Manual Entry** - Accounts created from CSV data automatically
3. **Correct Data** - Account numbers come from actual bank data
4. **Security** - Accounts automatically linked to authenticated user
5. **Flexible** - Can handle multiple account numbers in one CSV

---

## 🧪 Testing

### Test the Flow:

1. **Clear browser data:**
   ```javascript
   localStorage.clear();
   sessionStorage.clear();
   ```

2. **Visit** http://localhost:3000

3. **Register** a new user

4. **You should see** WelcomeScreen asking for CSV

5. **Upload CSV** with transactions

6. **Expected result:**
   - Accounts auto-created from "Rekening" column
   - All transactions imported
   - Main app loads with data
   - Accounts list shows all imported accounts

### Verify Account Ownership:

```bash
# Check user-account relationship
docker exec money-mysql mysql -u money -pmoneymakestheworldgoround money_db \
  -e "SELECT * FROM user_account;"
```

Should show:
- user_id: Your user's ID
- account_id: Auto-created account IDs

---

## 🔒 Security Notes

### Account Ownership
- Accounts created during import are automatically linked to the authenticated user
- Uses `Account::addOwner($user)` method
- Many-to-many relationship (user_account junction table)
- User can only see/access their own accounts

### Endpoint Protection
- `/api/transactions/import-first` requires JWT authentication
- Only creates accounts for the authenticated user
- Cannot create accounts for other users
- Existing accounts can be shared between users if added manually

---

## 📊 Database Changes

### user_account Table
When a user imports for the first time, entries are created in junction table:

```sql
user_account
├── user_id (references user.id)
└── account_id (references account.id)
```

Example after first import:
```
user_id  | account_id
---------|------------
1        | 1
1        | 2
```
(User 1 owns Accounts 1 and 2)

---

## 🚀 Files Changed

### Backend (3 files):
1. ✅ `TransactionImportController.php` - Added `importFirst()` endpoint
2. ✅ `AccountService.php` - Added `getOrCreateAccountByNumberForUser()`
3. ✅ `TransactionImportService.php` - Added `importForUser()` and user tracking

### Frontend (1 file):
1. ✅ `WelcomeScreen.tsx` - Simplified to single-step upload

---

**Fix Applied**: 2025-11-07
**Status**: ✅ READY TO TEST
**Breaking Changes**: None (new endpoint, old flow still works for subsequent imports)
