# Security Guide

**Last Updated:** January 2026

This guide documents the security measures implemented in Mister Munney.

## Authentication

### JWT-Based Authentication

The application uses JSON Web Tokens (JWT) for stateless authentication:

- **Algorithm**: RSA (RS256) with 4096-bit keys
- **Token Lifetime**: Configured in `lexik_jwt_authentication.yaml`
- **Key Storage**: `backend/config/jwt/` (private.pem, public.pem)

### Authentication Flow

1. User submits credentials to `POST /api/login`
2. Backend validates credentials against database
3. On success, JWT token is returned
4. Client includes token in subsequent requests: `Authorization: Bearer <token>`
5. Backend validates token signature and expiration

### Password Security

- **Algorithm**: Argon2id (strongest available)
- **Configuration**:
  - Memory cost: 64 MB
  - Time cost: 4 iterations
  - Threads: Automatically detected

```yaml
# security.yaml
password_hashers:
    App\User\Entity\User:
        algorithm: argon2id
        memory_cost: 65536
        time_cost: 4
```

## Account Security

### Login Protection

#### Rate Limiting

Login attempts are rate-limited to prevent brute force attacks:

- **Limit**: 5 attempts per 5 minutes per IP
- **Exponential backoff**: Wait time increases with failed attempts
- **Implementation**: `LoginRateLimitListener`

#### Failed Login Tracking

Every failed login is recorded:

```php
// LoginAttempt entity
- id
- email
- ipAddress
- userAgent
- attemptedAt
- successful
- failureReason
```

#### Account Lockout

After multiple failed attempts:

1. **3-4 failures**: Warning displayed
2. **5 failures**: CAPTCHA required
3. **Continued failures**: Account locked
4. **Unlock**: Via email link or automatic after timeout

### CAPTCHA Integration

hCaptcha integration for login protection:

- Triggered after suspicious activity
- Required for registration
- Server-side verification via `CaptchaService`

## API Security

### Rate Limiting

All API endpoints are rate-limited:

- **General API**: 100 requests per minute per IP
- **Login endpoint**: 5 attempts per 5 minutes per IP

Rate limit headers in responses:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1705700000
```

### CORS Configuration

Cross-Origin Resource Sharing is configured to allow only specific origins:

```yaml
# nelmio_cors.yaml
nelmio_cors:
    defaults:
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_headers: ['Content-Type', 'Authorization']
        allow_methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
```

### Input Validation

All input is validated using:

- Symfony Validator constraints
- Zod schemas (frontend)
- Database constraints

## Data Protection

### Multi-Tenancy Security

Users can only access their own data:

1. **Account Ownership**: Each account has an owner
2. **Account Sharing**: Explicit sharing with role-based access
3. **Query Filters**: All queries filtered by authenticated user

```php
// Every query includes user filter
$transactions = $repository->findBy([
    'account' => $this->getAuthorizedAccount($accountId, $user)
]);
```

### Access Control Hierarchy

| Role | Permissions |
|------|-------------|
| Owner | Full control, can share |
| Shared (Active) | Read/write transactions |
| Shared (Pending) | No access until accepted |
| Shared (Revoked) | No access |

### Sensitive Data Handling

- **Passwords**: Never stored in plain text, Argon2id hashed
- **API Keys**: Stored in environment variables, not in code
- **Account Numbers**: Masked in logs and error messages
- **Financial Data**: Encrypted at rest (database level)

## Logging & Monitoring

### Business Logging

Critical actions are logged via `BusinessLogger`:

- User logins (successful and failed)
- Account sharing changes
- Bulk operations
- Security events

```php
$this->businessLogger->info('User logged in', [
    'user_id' => $user->getId(),
    'ip' => $request->getClientIp(),
]);
```

### Request Logging

All API requests are logged via `RequestLoggerSubscriber`:

- Request method and path
- Response status code
- Processing time
- User ID (if authenticated)

### Audit Trail (AuditLog Entity)

Security-relevant actions are persisted to the `audit_logs` database table via `AuditLogService`:

**Tracked Actions:**
- Login/logout events
- Failed login attempts (with masked email)
- Account lock/unlock events
- Password changes
- Account sharing (share, revoke, accept)
- Transaction imports
- Bulk categorization
- Pattern creation/deletion
- Data exports

**AuditLog Fields:**
```php
- id: Auto-generated ID
- user: Reference to User (nullable)
- action: Action type constant
- entityType: Type of entity affected (e.g., 'Account', 'Pattern')
- entityId: ID of affected entity
- details: JSON metadata (e.g., count, masked email)
- ipAddress: Client IP address
- userAgent: Browser user agent
- createdAt: Timestamp
```

**Usage Example:**
```php
// Inject AuditLogService
$this->auditLogService->logLogin($user);
$this->auditLogService->logTransactionImport($user, $accountId, $count);
$this->auditLogService->logDataExport($user, 'csv', $recordCount);
```

**Cleanup:**
Old audit logs can be cleaned up (default: 90 days):
```php
$deleted = $this->auditLogService->cleanupOldLogs(90);
```

## Security Headers

Nginx configuration includes security headers:

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'; ..." always;
```

## HTTPS/TLS

All production traffic is encrypted:

- **Certificate**: Let's Encrypt (auto-renewed)
- **TLS Version**: 1.2 minimum
- **HSTS**: Enabled with max-age

Traefik handles SSL termination:

```yaml
# docker-compose.prod.yml
labels:
  - "traefik.http.routers.munney.tls=true"
  - "traefik.http.routers.munney.tls.certresolver=letsencrypt"
```

## Dependency Security

### Regular Auditing

```bash
# Backend (PHP)
composer audit

# Frontend (JavaScript)
npm audit
```

### CI Integration

Security scanning runs on every CI build:

```yaml
# ci.yml
- name: Check for security vulnerabilities
  run: composer audit
```

## Security Checklist

### Development

- [ ] No secrets in code
- [ ] Environment variables for configuration
- [ ] Input validation on all endpoints
- [ ] SQL injection prevention (Doctrine ORM)
- [ ] XSS prevention (React auto-escaping)
- [ ] CSRF protection (stateless API)

### Deployment

- [ ] HTTPS enabled
- [ ] Strong passwords for database
- [ ] JWT keys properly generated
- [ ] Rate limiting configured
- [ ] Security headers set
- [ ] Backups enabled and encrypted

### Operations

- [ ] Regular security updates
- [ ] Log monitoring
- [ ] Failed login alerting
- [ ] Backup verification
- [ ] Access review

## Vulnerability Reporting

If you discover a security vulnerability:

1. **Do not** create a public GitHub issue
2. Contact the maintainer directly
3. Provide detailed reproduction steps
4. Allow reasonable time for fix

## Common Security Threats

### OWASP Top 10 Mitigations

| Threat | Mitigation |
|--------|------------|
| **Injection** | Doctrine ORM, parameterized queries |
| **Broken Auth** | JWT, Argon2id, rate limiting |
| **Sensitive Data** | Encryption, HTTPS, hashing |
| **XXE** | Disabled XML parsing |
| **Access Control** | Per-user data filtering |
| **Misconfiguration** | Secure defaults, prod settings |
| **XSS** | React escaping, CSP headers |
| **Deserialization** | JSON only, no PHP serialization |
| **Vulnerabilities** | Composer/npm audit |
| **Logging** | BusinessLogger, request logging |

## Related Documentation

- [01_PROJECT_OVERVIEW.md](01_PROJECT_OVERVIEW.md) - Application architecture
- [11_DEPLOYMENT_GUIDE.md](11_DEPLOYMENT_GUIDE.md) - Secure deployment
- [10_API_DOCUMENTATION.md](10_API_DOCUMENTATION.md) - API authentication
