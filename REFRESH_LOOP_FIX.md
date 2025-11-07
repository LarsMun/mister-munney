# Refresh Loop Fix

**Issue**: Frontend was stuck in an infinite refresh loop
**Date**: 2025-11-07
**Status**: ✅ FIXED

---

## 🐛 The Problem

The application was refreshing infinitely when first loaded, preventing users from seeing the login screen.

### Root Cause

The issue was a race condition between authentication and account fetching:

1. **App loads** → AuthProvider initializes (user not authenticated)
2. **AccountProvider initializes** → Immediately calls `refreshAccounts()` on mount
3. **API call** → `GET /api/accounts` returns **401 Unauthorized** (no token)
4. **Axios interceptor** → Detects 401, clears localStorage, reloads page with `window.location.href = '/'`
5. **Loop repeats** → Back to step 1

---

## ✅ The Solution

### 1. **Removed Automatic Account Fetch on Mount**

**File**: `frontend/src/app/context/AccountContext.tsx`

**Before:**
```typescript
const [isLoading, setIsLoading] = useState(true);

// Initial load
useEffect(() => {
    refreshAccounts();  // ❌ Called immediately, before auth check
}, []);
```

**After:**
```typescript
const [isLoading, setIsLoading] = useState(false);

// Don't automatically fetch on mount - let App.tsx trigger this after auth check
// Initial load is now handled by App.tsx when user is authenticated
```

### 2. **Added Controlled Account Fetch in App.tsx**

**File**: `frontend/src/App.tsx`

**Added:**
```typescript
// Fetch accounts only when user is authenticated
useEffect(() => {
    if (isAuthenticated && !authLoading) {
        refreshAccounts();
    }
}, [isAuthenticated, authLoading, refreshAccounts]);
```

This ensures accounts are only fetched **after** the user is authenticated.

### 3. **Improved Axios Interceptor to Prevent Loops**

**File**: `frontend/src/lib/axios.ts`

**Before:**
```typescript
if (error.response?.status === 401) {
    localStorage.removeItem('munney_jwt_token');
    localStorage.removeItem('munney_user');
    window.location.href = '/';  // ❌ Always reloads, even if no token
}
```

**After:**
```typescript
if (error.response?.status === 401) {
    const hadToken = localStorage.getItem('munney_jwt_token');
    if (hadToken) {  // ✅ Only reload if we had a token
        localStorage.removeItem('munney_jwt_token');
        localStorage.removeItem('munney_user');
        window.location.href = '/';
    }
}
```

This prevents the reload loop when there's no token to begin with.

---

## 📋 Files Changed

1. ✅ `frontend/src/app/context/AccountContext.tsx`
   - Changed `isLoading` initial state from `true` to `false`
   - Removed automatic `useEffect` that called `refreshAccounts()` on mount

2. ✅ `frontend/src/App.tsx`
   - Added `useEffect` import
   - Added controlled account fetch that only runs when authenticated

3. ✅ `frontend/src/lib/axios.ts`
   - Improved 401 interceptor to check if token exists before reloading

---

## 🧪 Testing

### Before Fix:
- ❌ Page refreshes infinitely
- ❌ Cannot see login screen
- ❌ Console shows repeated 401 errors from `/api/accounts`

### After Fix:
- ✅ Page loads once
- ✅ Login screen displays correctly
- ✅ No console errors on initial load
- ✅ Accounts fetch only after successful login

---

## 🎯 How the Flow Works Now

### First Visit (Not Authenticated)
```
1. App loads
2. AuthProvider checks localStorage → No token
3. isAuthenticated = false
4. App.tsx renders AuthScreen (login/register)
5. No API calls made yet ✅
```

### After Login
```
1. User enters credentials
2. Login successful → Token stored in localStorage
3. AuthProvider sets isAuthenticated = true
4. App.tsx useEffect triggers → Calls refreshAccounts()
5. Accounts fetched with valid token ✅
6. WelcomeScreen or Main App displayed
```

### Token Expiry (During Use)
```
1. User makes API call with expired token
2. Backend returns 401
3. Axios interceptor checks → Token exists
4. Clear token + reload page
5. User sees login screen ✅
```

---

## 💡 Key Lessons

1. **Don't fetch data before authentication** - Always check auth state first
2. **Control the fetch trigger** - Don't let child contexts fetch independently
3. **Prevent reload loops** - Check if token exists before reloading on 401
4. **Order matters** - AuthProvider → Check auth → Fetch accounts

---

## 🚀 Verification

To verify the fix is working:

1. **Clear browser data:**
   ```javascript
   localStorage.clear();
   sessionStorage.clear();
   ```

2. **Visit**: http://localhost:3000

3. **Expected behavior:**
   - ✅ Page loads once (no refreshing)
   - ✅ Login/Register screen displays
   - ✅ No console errors
   - ✅ No network calls to `/api/accounts` yet

4. **After login:**
   - ✅ Accounts fetched automatically
   - ✅ WelcomeScreen or Main App displays

---

**Fix Applied**: 2025-11-07
**Status**: ✅ VERIFIED WORKING
