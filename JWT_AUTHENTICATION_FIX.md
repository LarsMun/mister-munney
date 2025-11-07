# JWT Authentication Fix - Frontend Service Conversion

**Date**: 2025-11-07
**Issue**: Dashboard and other features showing "401 JWT token not found" errors
**Root Cause**: Frontend services using raw `fetch()` instead of axios instance with JWT interceptors
**Fix**: Converted all service files to use axios for automatic JWT token inclusion

---

## 🐛 The Problem

After implementing JWT authentication, users reported getting "401 JWT token not found" errors when viewing the dashboard and using other features. The root cause was that many frontend service files were using raw `fetch()` calls instead of the axios instance configured with JWT interceptors.

**Why this matters**:
- The axios instance in `lib/axios.ts` has request interceptors that automatically add the JWT token from localStorage
- Raw `fetch()` calls don't include the Authorization header automatically
- All API endpoints now require JWT authentication (except `/login` and `/register`)

---

## ✅ The Solution

### Files Converted to Use Axios

#### 1. Budget Dashboard Services
**File**: `frontend/src/domains/budgets/services/AdaptiveDashboardService.ts`

**Converted Functions**:
- `fetchActiveBudgets()`
- `fetchOlderBudgets()`
- `fetchProjects()`
- `createProject()`
- `updateProject()`
- `fetchProjectDetails()`
- `createExternalPayment()`
- `updateExternalPayment()`
- `deleteExternalPayment()`
- `removeExternalPaymentAttachment()`
- `uploadExternalPaymentAttachment()`
- `fetchProjectEntries()`
- `fetchProjectExternalPayments()`
- `fetchProjectAttachments()`
- `uploadProjectAttachment()`
- `deleteProjectAttachment()`

**Before**:
```typescript
const response = await fetch(`${prefix}/budgets/active?${params.toString()}`);
if (!response.ok) {
    throw new Error('Failed to fetch active budgets');
}
return await response.json();
```

**After**:
```typescript
const response = await api.get(`/budgets/active?${params.toString()}`);
return response.data;
```

#### 2. Transaction Services

**File**: `frontend/src/domains/transactions/services/PayPalService.ts`

**Converted Functions**:
- `importPayPalTransactions()`

**Before**:
```typescript
const response = await fetch(`${prefix}/transactions/import-paypal`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ accountId, pastedText }),
});
if (!response.ok) {
    throw new Error('Failed to import PayPal transactions');
}
return await response.json();
```

**After**:
```typescript
const response = await api.post('/transactions/import-paypal', {
    accountId,
    pastedText,
});
return response.data;
```

**File**: `frontend/src/domains/transactions/services/TransactionSplitService.ts`

**Converted Functions**:
- `parseCreditCardPdf()`
- `createSplits()`
- `getSplits()`
- `deleteSplits()`
- `deleteSplit()`

**Before**:
```typescript
const response = await fetch(
    `${prefix}/account/${accountId}/transaction/${transactionId}/splits`,
    {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ splits }),
    }
);
if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to create splits');
}
return await response.json();
```

**After**:
```typescript
const response = await api.post(
    `/account/${accountId}/transaction/${transactionId}/splits`,
    { splits }
);
return response.data;
```

#### 3. Pattern Discovery Component

**File**: `frontend/src/domains/patterns/components/PatternDiscovery.tsx`

**Converted Sections**:
- Pattern matching fetch call

**Before**:
```typescript
const response = await fetch(`${API_PREFIX}/account/${accountId}/patterns/match`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        accountId: accountId,
        description: descriptionPattern,
        matchTypeDescription: descriptionPattern ? 'LIKE' : null,
        notes: notesPattern,
        matchTypeNotes: notesPattern ? 'LIKE' : null,
    })
});
if (!response.ok) {
    throw new Error('Failed to load matching transactions');
}
const result = await response.json();
```

**After**:
```typescript
const response = await api.post(`/account/${accountId}/patterns/match`, {
    accountId: accountId,
    description: descriptionPattern,
    matchTypeDescription: descriptionPattern ? 'LIKE' : null,
    notes: notesPattern,
    matchTypeNotes: notesPattern ? 'LIKE' : null,
});
const result = response.data;
```

---

## 🔄 Conversion Patterns

### GET Requests
```typescript
// Before
const response = await fetch(`${prefix}/endpoint`);
if (!response.ok) throw new Error('...');
return await response.json();

// After
const response = await api.get('/endpoint');
return response.data;
```

### POST Requests (JSON)
```typescript
// Before
const response = await fetch(`${prefix}/endpoint`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
});
if (!response.ok) throw new Error('...');
return await response.json();

// After
const response = await api.post('/endpoint', data);
return response.data;
```

### POST Requests (FormData)
```typescript
// Before
const response = await fetch(`${prefix}/endpoint`, {
    method: 'POST',
    body: formData,
});
if (!response.ok) throw new Error('...');
return await response.json();

// After
const response = await api.post('/endpoint', formData, {
    headers: {
        'Content-Type': 'multipart/form-data',
    },
});
return response.data;
```

### DELETE Requests
```typescript
// Before
const response = await fetch(`${prefix}/endpoint/${id}`, {
    method: 'DELETE',
});
if (!response.ok) throw new Error('...');

// After
await api.delete(`/endpoint/${id}`);
```

### PATCH Requests
```typescript
// Before
const response = await fetch(`${prefix}/endpoint/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
});
if (!response.ok) throw new Error('...');
return await response.json();

// After
const response = await api.patch(`/endpoint/${id}`, data);
return response.data;
```

---

## 🎯 Benefits of Axios Instance

1. **Automatic JWT Tokens**: Request interceptor adds Authorization header to all requests
2. **Automatic Logout on 401**: Response interceptor clears auth and redirects on token expiration
3. **Consistent Error Handling**: Axios throws on non-2xx responses, no need for `if (!response.ok)` checks
4. **Cleaner Code**: Less boilerplate for headers and error handling
5. **TypeScript Support**: Better type inference with axios

---

## 🧪 Testing

### How to Test the Fix:

1. **Clear browser data**:
   ```javascript
   localStorage.clear();
   sessionStorage.clear();
   ```

2. **Visit** http://localhost:3000

3. **Login** with your credentials

4. **Navigate to dashboard**:
   - Should see active budgets with insights
   - Should see older budgets panel
   - Should see projects section (if feature flag enabled)

5. **Test other features**:
   - PayPal import (should not get 401)
   - Transaction splitting (should not get 401)
   - Pattern discovery (should not get 401)

6. **Expected behavior**:
   - All API calls include JWT token automatically
   - No 401 errors on authenticated pages
   - If token expires, automatic logout and redirect to login

### Verify JWT Token is Sent:

Open browser DevTools → Network tab → Select any API request → Headers:
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

---

## 📝 Axios Instance Configuration

The axios instance is configured in `frontend/src/lib/axios.ts`:

```typescript
import axios from 'axios';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8787/api';

const axiosInstance = axios.create({
    baseURL: API_URL,
    timeout: 30000,
});

// Add JWT token to all requests
axiosInstance.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('munney_jwt_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

// Handle 401 responses (token expired or invalid)
axiosInstance.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            const hadToken = localStorage.getItem('munney_jwt_token');
            if (hadToken) {
                localStorage.removeItem('munney_jwt_token');
                localStorage.removeItem('munney_user');
                window.location.href = '/';
            }
        }
        return Promise.reject(error);
    }
);

export default axiosInstance;
```

---

## 🚀 Files Changed

### Frontend (4 files):
1. ✅ `domains/budgets/services/AdaptiveDashboardService.ts` - Converted 16 functions
2. ✅ `domains/transactions/services/PayPalService.ts` - Converted 1 function
3. ✅ `domains/transactions/services/TransactionSplitService.ts` - Converted 5 functions
4. ✅ `domains/patterns/components/PatternDiscovery.tsx` - Converted 1 fetch call

---

## 🔍 Finding Remaining fetch() Calls

To check for any remaining raw fetch calls:

```bash
# Search for fetch calls in TypeScript files
grep -rn "await fetch(" frontend/src --include="*.ts" --include="*.tsx"

# Expected result: Only commented-out lines or non-API fetch calls
```

---

## 📊 Results

**Before Fix**:
- ❌ Dashboard: 401 JWT token not found
- ❌ Projects: 401 JWT token not found
- ❌ PayPal Import: 401 JWT token not found
- ❌ Transaction Splits: 401 JWT token not found
- ❌ Pattern Discovery: 401 JWT token not found

**After Fix**:
- ✅ Dashboard: Loads successfully with insights
- ✅ Projects: Loads successfully with aggregations
- ✅ PayPal Import: Works with authentication
- ✅ Transaction Splits: Works with authentication
- ✅ Pattern Discovery: Works with authentication

---

## 🔒 Security Notes

### JWT Token Flow
1. User logs in via `/api/login` → receives JWT token
2. Token stored in localStorage (`munney_jwt_token`)
3. Axios interceptor adds token to every API request
4. Backend validates token on every request
5. If token invalid/expired → 401 response
6. Axios interceptor catches 401 → clears token → redirects to login

### Token Expiration
- Tokens expire after configured time (default: 1 hour)
- On expiration, user is automatically logged out
- User must re-authenticate to get new token

### Account Ownership
- All API endpoints verify account ownership via `verifyAccountOwnership()`
- User can only access their own accounts/budgets/categories/transactions
- 403 Forbidden if user tries to access another user's data

---

**Fix Applied**: 2025-11-07
**Status**: ✅ COMPLETE
**Breaking Changes**: None (internal refactor only)
**Deployed**: Requires frontend rebuild (`docker compose build frontend && docker compose up -d frontend`)
