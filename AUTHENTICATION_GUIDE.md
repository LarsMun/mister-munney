# Authentication Guide - Munney Application

## Overview
Your application uses JWT (JSON Web Token) authentication with email/password login.

---

## 🔐 API Endpoints

### 1. Register a New User

**Endpoint:** `POST /api/register`

**Request Body:**
```json
{
  "email": "your.email@example.com",
  "password": "your_secure_password"
}
```

**Requirements:**
- Email must be valid format
- Password must be at least 8 characters
- Email must be unique (not already registered)

**Success Response (201 Created):**
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "email": "your.email@example.com",
    "createdAt": "2025-01-07 12:00:00"
  }
}
```

**Error Responses:**
- `400 Bad Request` - Invalid email or password too short
- `409 Conflict` - Email already registered

---

### 2. Login (Get JWT Token)

**Endpoint:** `POST /api/login`

**Request Body:**
```json
{
  "email": "your.email@example.com",
  "password": "your_secure_password"
}
```

**Success Response (200 OK):**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "def50200..."
}
```

**Error Response (401 Unauthorized):**
```json
{
  "code": 401,
  "message": "Invalid credentials."
}
```

---

## 📝 Usage Examples

### Using cURL

**Register:**
```bash
curl -X POST http://localhost:8787/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "test12345"
  }'
```

**Login:**
```bash
curl -X POST http://localhost:8787/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "test12345"
  }'
```

**Use JWT Token for API Calls:**
```bash
# Save token from login response
TOKEN="your_jwt_token_here"

# Make authenticated request
curl -X GET http://localhost:8787/api/accounts \
  -H "Authorization: Bearer $TOKEN"
```

---

### Using Frontend (JavaScript/TypeScript)

**Register:**
```javascript
const register = async (email, password) => {
  const response = await fetch('http://localhost:8787/api/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error);
  }

  return await response.json();
};
```

**Login:**
```javascript
const login = async (email, password) => {
  const response = await fetch('http://localhost:8787/api/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });

  if (!response.ok) {
    throw new Error('Invalid credentials');
  }

  const data = await response.json();
  // Store token in localStorage or state management
  localStorage.setItem('token', data.token);
  return data;
};
```

**Authenticated API Calls:**
```javascript
const fetchAccounts = async () => {
  const token = localStorage.getItem('token');

  const response = await fetch('http://localhost:8787/api/accounts', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });

  if (response.status === 401) {
    // Token expired or invalid - redirect to login
    window.location.href = '/login';
    return;
  }

  return await response.json();
};
```

---

## 🔑 JWT Token Details

**Token Contents:**
- User ID
- Email
- Roles
- Expiration time (default: 1 hour)

**Token Format:**
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Token Storage Recommendations:**
- Frontend: localStorage or sessionStorage
- Never store in cookies without httpOnly flag
- Always use HTTPS in production
- Clear token on logout

---

## 🔒 Security Notes

### Password Requirements
- Minimum 8 characters
- Hashed using Argon2id (most secure algorithm)
- Hash parameters:
  - Memory cost: 64 MB
  - Time cost: 4

### JWT Configuration
- Tokens are stateless
- No server-side session storage
- Tokens expire after configured time
- Refresh tokens can be used to get new tokens

### Protected Endpoints
ALL endpoints under `/api/*` require authentication EXCEPT:
- `/api/login` - Public (login endpoint)
- `/api/register` - Public (registration)
- `/api/doc*` - Public (API documentation)
- `/api/icons*` - Public (static icon files)

---

## 🚀 Quick Start Flow

### 1. Register a new user
```bash
curl -X POST http://localhost:8787/api/register \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@example.com","password":"demo12345"}'
```

### 2. Login to get JWT token
```bash
curl -X POST http://localhost:8787/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@example.com","password":"demo12345"}'
```

### 3. Copy the token from response

### 4. Use token for API calls
```bash
curl -X GET http://localhost:8787/api/accounts \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 🐛 Troubleshooting

### "Invalid credentials" on login
- Check email and password are correct
- Verify user is registered
- Check database: `docker exec money-mysql mysql -u money -pmoneymakestheworldgoround money_db -e "SELECT * FROM user;"`

### "401 Unauthorized" on API calls
- Token might be expired (default: 1 hour)
- Token might be invalid or tampered with
- Missing `Bearer` prefix in Authorization header
- Login again to get fresh token

### "LoaderLoadException" error
- This has been fixed by clearing cache
- If it persists: `docker exec money-backend php bin/console cache:clear`

### User table doesn't exist
- Run migrations: `docker exec money-backend php bin/console doctrine:migrations:migrate`

---

## 📊 Database

**User Table Structure:**
```sql
CREATE TABLE user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(180) UNIQUE NOT NULL,
    roles JSON NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY UNIQ_USER_EMAIL (email)
);
```

**User-Account Relationship:**
```sql
CREATE TABLE user_account (
    user_id INT NOT NULL,
    account_id INT NOT NULL,
    PRIMARY KEY (user_id, account_id),
    FOREIGN KEY (user_id) REFERENCES user(id),
    FOREIGN KEY (account_id) REFERENCES account(id)
);
```

**Note:** Users can have multiple accounts, and accounts can belong to multiple users.

---

## 🔄 Account Ownership

After registering and logging in, you need to:
1. Create an account or get assigned to one
2. All API calls verify you own the account you're accessing
3. You can only access data for accounts you own

**Check your accounts:**
```bash
curl -X GET http://localhost:8787/api/accounts \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Create an account:**
```bash
curl -X POST http://localhost:8787/api/accounts \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"My Account","accountNumber":"NL91ABNA0417164300","isDefault":true}'
```

---

## 📝 Frontend Integration Checklist

- [ ] Create login page/form
- [ ] Create registration page/form
- [ ] Store JWT token after successful login
- [ ] Add Authorization header to all API requests
- [ ] Handle 401 responses (redirect to login)
- [ ] Clear token on logout
- [ ] Show user email/info when logged in
- [ ] Test with multiple users

---

## 🎯 Next Steps

1. **Test Authentication:**
   - Register a user via Postman/curl
   - Login to get JWT token
   - Make authenticated API calls

2. **Create Account:**
   - After login, create your first account
   - This account will be linked to your user

3. **Import Transactions:**
   - Use the new secure import endpoints
   - `/api/account/{accountId}/transactions/import`

4. **Frontend Development:**
   - Build login/register pages
   - Implement token storage
   - Add auth headers to API calls

---

**Documentation Updated:** 2025-01-07
**Backend URL:** http://localhost:8787
**Frontend URL:** http://localhost:3000
