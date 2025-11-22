# SECURITY AUDIT - DETAILED FINDINGS
## Mister Munney Application

**Date:** November 20, 2025
**Focus:** Secret management, authentication, deployment security
**Previous Audit:** November 6, 2025 (authentication implementation audit)

---

## 🔴 CRITICAL SECURITY VULNERABILITIES

### 1. HARDCODED API KEYS IN VERSION CONTROL

**Severity:** 🔴 **CRITICAL (CVSS 9.8)**
**CWE-798:** Use of Hard-coded Credentials
**Status:** ACTIVELY EXPLOITABLE

#### 1.1 Resend Email API Key Exposed

**Location:** `/docker-compose.yml:14`
```yaml
MAILER_DSN: "resend+api://re_UrrEVv6w_9NEHJayyB1VWJHB9g7bZcgfu@default"
```

**Attack Vector:**
- API key visible in committed code
- Anyone with repository access can abuse email sending
- No rate limits on Resend side if key is stolen
- Can send spam/phishing emails from `noreply@munney.munne.me`

**Financial Impact:**
- Resend pricing: $0.10 per 1,000 emails
- Attacker could send millions of emails
- Potential blacklisting of domain

**Evidence of Exposure:**
- File tracked in git since initial commit
- Visible in git log: `git log --all -p docker-compose.yml | grep "re_"`
- Same key used in production: `/srv/munney-prod/.env:MAILER_DSN`

#### 1.2 OpenAI API Key Exposed

**Location:** `/srv/munney-prod/.env` (server file)
```bash
OPENAI_API_KEY=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
```

**Attack Vector:**
- Full OpenAI API key exposed (sk-proj prefix = project key)
- Can incur unlimited charges against your OpenAI account
- Used for AI pattern discovery and transaction categorization
- No spending limits configured on key

**Financial Impact:**
- GPT-4 pricing: ~$0.03-0.12 per 1K tokens
- Attacker could drain thousands of dollars
- Recent case study: $10K+ bill from stolen API key

**Verification:**
```bash
# Test if key is valid (DON'T RUN - just example)
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer sk-proj-MjDnta3..."
# If returns model list, key is valid
```

#### 1.3 hCaptcha Secret Key Exposed

**Location:** `/backend/.env` (local dev)
```bash
HCAPTCHA_SECRET_KEY=ES_e9abae79ed0f4f448f3ef6994d0af93b
```

**Attack Vector:**
- Allows verification of any CAPTCHA response
- Bypasses brute force protection
- Can automate login attempts despite rate limiting

**Impact:**
- Login attempt security feature rendered useless
- Account lock mechanism can be bypassed
- Automated credential stuffing attacks possible

#### 1.4 JWT Passphrase Hardcoded

**Location:** `/deploy/ubuntu/docker-compose.prod.yml:27`
```yaml
JWT_PASSPHRASE: '+Qdsl7gdFOYMlixhppIKftcHetoUa/G2gxZBBLOx9Is='
```

**Attack Vector:**
- JWT tokens signed with this passphrase
- Anyone with passphrase can forge valid JWT tokens
- Can impersonate any user
- Can bypass all authentication

**Impact:**
- Complete authentication bypass
- Ability to forge tokens for any user
- Session hijacking trivial
- No way to invalidate compromised tokens (stateless JWT)

**Verification:**
```bash
# Check if JWT uses this passphrase
openssl rsa -in backend/config/jwt/private.pem -passin pass:'+Qdsl7gdFOYMlixhppIKftcHetoUa/G2gxZBBLOx9Is=' -text
# If succeeds, passphrase is correct
```

---

### IMMEDIATE REMEDIATION STEPS

#### Step 1: Revoke ALL Compromised Keys (Do First!)

```bash
# 1. Revoke Resend API Key
# Login to https://resend.com/api-keys
# Delete key: re_UrrEVv6w_9NEHJayyB1VWJHB9g7bZcgfu
# Generate new key and store securely

# 2. Rotate OpenAI API Key
# Login to https://platform.openai.com/api-keys
# Revoke key starting with: sk-proj-MjDnta3M52e6w4w...
# Generate new project key with spending limits

# 3. Generate new hCaptcha keys
# Login to https://www.hcaptcha.com/
# Regenerate site and secret keys

# 4. Generate new JWT keypair
cd /srv/munney-prod/backend
rm config/jwt/*.pem
JWT_PASSPHRASE=$(openssl rand -base64 32)
echo "New JWT_PASSPHRASE: $JWT_PASSPHRASE"
openssl genpkey -algorithm RSA -out config/jwt/private.pem \
  -aes256 -pass pass:"$JWT_PASSPHRASE" -pkeyopt rsa_keygen_bits:4096
openssl rsa -pubout -in config/jwt/private.pem \
  -out config/jwt/public.pem -passin pass:"$JWT_PASSPHRASE"
```

#### Step 2: Remove Secrets from Git History

```bash
# Install BFG Repo-Cleaner
brew install bfg  # or download from https://rtyley.github.io/bfg-repo-cleaner/

# Create file with secrets to remove
cat > secrets-to-remove.txt << EOF
re_UrrEVv6w_9NEHJayyB1VWJHB9g7bZcgfu
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
ES_e9abae79ed0f4f448f3ef6994d0af93b
+Qdsl7gdFOYMlixhppIKftcHetoUa/G2gxZBBLOx9Is=
moneymakestheworldgoround
EOF

# Run BFG to remove secrets
bfg --replace-text secrets-to-remove.txt ~/dev/money

# Clean up
cd ~/dev/money
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# Force push (DANGEROUS - coordinate with team!)
git push --force --all
git push --force --tags
```

**⚠️ WARNING:** Force pushing rewrites history. Coordinate with all team members first!

#### Step 3: Update .gitignore

```bash
# Add to .gitignore
cat >> .gitignore << EOF

# Environment files with secrets
.env
.env.local
.env.*.local
backend/.env
backend/.env.local
backend/.env.*.local
frontend/.env.local
frontend/.env.production

# Backup files (often contain secrets)
*.backup
*.env.backup*

# JWT keys
backend/config/jwt/*.pem
EOF

git add .gitignore
git commit -m "security: Prevent secret files from being committed"
```

#### Step 4: Migrate to Environment Variables

**Create `.env.local` (NOT tracked in git):**
```bash
# /home/lars/dev/money/.env.local
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
MYSQL_PASSWORD=$(openssl rand -base64 32)
OPENAI_API_KEY=sk-proj-YOUR_NEW_KEY_HERE
RESEND_API_KEY=re_YOUR_NEW_KEY_HERE
HCAPTCHA_SECRET_KEY=ES_YOUR_NEW_KEY_HERE
JWT_PASSPHRASE=$(openssl rand -base64 32)
```

**Update docker-compose.yml:**
```yaml
services:
  backend:
    environment:
      DATABASE_URL: "mysql://money:${MYSQL_PASSWORD}@database:3306/money_db?serverVersion=8.0"
      MAILER_DSN: "resend+api://${RESEND_API_KEY}@default"
      OPENAI_API_KEY: ${OPENAI_API_KEY}
      JWT_PASSPHRASE: ${JWT_PASSPHRASE}

  database:
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
```

**For production, use Docker secrets:**
```yaml
# deploy/ubuntu/docker-compose.prod.yml
services:
  backend:
    environment:
      DATABASE_URL: "mysql://money:${MYSQL_PASSWORD_PROD}@database:3306/money_db_prod"
      OPENAI_API_KEY_FILE: /run/secrets/openai_api_key
      MAILER_DSN_FILE: /run/secrets/mailer_dsn
      JWT_PASSPHRASE_FILE: /run/secrets/jwt_passphrase
    secrets:
      - openai_api_key
      - mailer_dsn
      - jwt_passphrase

secrets:
  openai_api_key:
    file: ./secrets/openai_api_key.txt
  mailer_dsn:
    file: ./secrets/mailer_dsn.txt
  jwt_passphrase:
    file: ./secrets/jwt_passphrase.txt
```

---

## 🔴 CRITICAL: MISSING ENVIRONMENT VARIABLES IN PRODUCTION

### 2. HCAPTCHA_SECRET_KEY NOT IN PRODUCTION

**Severity:** 🔴 **HIGH (Application Breaking)**
**Status:** ACTIVE BUG

**Evidence:**
- Service configuration requires it: `backend/config/services.yaml:63`
- Present in local dev: `backend/.env`
- **MISSING** in `/srv/munney-prod/.env`
- **MISSING** in `/srv/munney-dev/.env`

**Impact:**
- CAPTCHA verification fails after 3 failed login attempts
- Users cannot log in after triggering rate limit
- `CaptchaService::verify()` will return false (token is empty)
- Login attempt tracking works, but enforcement breaks

**Flow:**
1. User fails login 3 times
2. `LoginAttemptListener::checkAccountLock()` requires CAPTCHA (line 71)
3. `CaptchaService::needsCaptcha()` returns true (line 27-30)
4. Frontend shows CAPTCHA
5. User solves CAPTCHA and submits
6. Backend calls `CaptchaService::verify()` (line 40)
7. **BUG:** `$this->hcaptchaSecretKey` is empty string
8. hCaptcha API returns error
9. User cannot login

**Immediate Fix:**
```bash
ssh lars@192.168.0.105
cd /srv/munney-prod

# Add to .env (but use NEW key after rotation!)
echo 'HCAPTCHA_SECRET_KEY=YOUR_NEW_SECRET_KEY' >> .env

# Restart backend
docker restart munney-prod-backend

# Verify
docker exec munney-prod-backend printenv | grep HCAPTCHA
```

**Test Plan:**
```bash
# 1. Attempt login with wrong password 3 times
curl -X POST https://munney.munne.me/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"wrong"}'

# 2. Verify CAPTCHA requirement returned
# Response should include: "requiresCaptcha": true

# 3. Attempt login with CAPTCHA token
# Should now work with valid CAPTCHA
```

---

## 🟡 HIGH PRIORITY SECURITY ISSUES

### 3. WEAK DATABASE CREDENTIALS

**Severity:** 🟡 **HIGH**
**Location:** `docker-compose.yml:46,49`, `docker-compose.prod.yml:68,70`

**Current Password:** `moneymakestheworldgoround`

**Weaknesses:**
- Dictionary word combinations (easy to crack)
- Same password for root and user
- Exposed in version control
- No special characters
- Only 30 characters (should be 32+ random)

**Crack Time Estimate:**
- Offline attack: < 1 hour with hashcat
- Online attack: Blocked by Docker network isolation (good!)
- But if attacker gets docker-compose.yml, it's game over

**Recommendation:**
```bash
# Generate strong passwords (32 bytes = 44 characters base64)
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
MYSQL_PASSWORD=$(openssl rand -base64 32)

# Example output:
# wK9x2Lp4Qn7Vc3Rf8Zh5Jm1Tb6Yg0Nd=
```

---

### 4. UnlockController::getUnlockStatus() RETURNS FAKE DATA

**Severity:** 🟡 **MEDIUM** (Security by Obscurity Attempt Gone Wrong)
**Location:** `backend/src/Security/Controller/UnlockController.php:170-188`

**Code:**
```php
public function getUnlockStatus(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $email = $data['email'] ?? null;

    if (!$email) {
        throw new BadRequestHttpException('Email is required');
    }

    // For security, don't reveal if user exists
    // Just return generic "not locked" response if user doesn't exist
    return $this->json([
        'locked' => false,
        'message' => 'Account is not locked'
    ]);

    // Note: Real implementation would check user and return lock status
    // But we're being cautious about revealing account existence
}
```

**Issues:**
1. **Always returns "not locked"** - regardless of actual status
2. **Unreachable code** after return statement (line 186-188)
3. **Frontend depends on this endpoint** but gets wrong data
4. **Comment suggests intentional** but this breaks functionality

**Impact:**
- Frontend cannot show lock status to user
- User doesn't know if account is locked without trying to login
- Poor user experience
- Defeats purpose of the endpoint

**Security Rationale (from comment):**
> "For security, don't reveal if user exists"

This is **security through obscurity** and actually makes things worse:
- Attacker can still enumerate users via timing attacks
- Legitimate users get confused
- Lock feature is harder to use

**Better Approach:**
```php
public function getUnlockStatus(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $email = $data['email'] ?? null;

    if (!$email) {
        throw new BadRequestHttpException('Email is required');
    }

    // Find user
    $user = $this->userRepository->findOneBy(['email' => $email]);

    // If user doesn't exist, return generic response
    if (!$user) {
        return $this->json([
            'locked' => false,
            'message' => 'Account is not locked'
        ]);
    }

    // If user exists, return ACTUAL lock status
    if ($this->accountLockService->isAccountLocked($user)) {
        $lockInfo = $this->accountLockService->getLockInfo($user);
        return $this->json([
            'locked' => true,
            'lockedAt' => $lockInfo['lockedAt']->format('c'),
            'message' => 'Account is locked. Check your email for unlock link.'
        ]);
    }

    return $this->json([
        'locked' => false,
        'message' => 'Account is not locked'
    ]);
}
```

**Fix Priority:** MEDIUM (Improves UX, low security impact)

---

## 🟢 MEDIUM/LOW PRIORITY FINDINGS

### 5. Multiple .env Backup Files on Production Server

**Location:** `/srv/munney-prod/.env.backup.20251120_161108`

**Risk:**
- Backup files may contain old secrets
- Can be accidentally served if web server is misconfigured
- Easy to forget to delete after changes

**Recommendation:**
```bash
# Remove all backup files
ssh lars@192.168.0.105
rm /srv/munney-prod/.env.backup*

# Use version control instead of manual backups
# Or use automated backup system that stores encrypted backups off-server
```

---

### 6. No Token Length Validation on Unlock

**Location:** `UnlockController::unlock()` line 63

**Code:**
```php
$token = $data['token'] ?? null;

if (!$token) {
    throw new BadRequestHttpException('Unlock token is required');
}

// Unlock account
$user = $this->accountLockService->unlockAccount($token);
```

**Issue:**
- No validation on token format or length
- Could pass very long string (DoS via memory)
- Could pass SQL injection attempts (though Doctrine protects)
- Should validate format matches generated tokens

**Recommendation:**
```php
$token = $data['token'] ?? null;

if (!$token || strlen($token) !== 64 || !ctype_alnum($token)) {
    throw new BadRequestHttpException('Invalid unlock token format');
}
```

**Expected Format:**
- Unlock tokens should be 64 hexadecimal characters (from sha256)
- Generated in `AccountLockService::lockAccount()` using `bin2hex(random_bytes(32))`

---

## 📋 SECURITY CHECKLIST

### Immediate Actions (Next 24 Hours)
- [ ] Revoke Resend API key `re_UrrEVv6w...`
- [ ] Rotate OpenAI API key
- [ ] Regenerate hCaptcha keys
- [ ] Generate new JWT keypair
- [ ] Add HCAPTCHA_SECRET_KEY to production
- [ ] Add HCAPTCHA_SECRET_KEY to dev
- [ ] Remove `.env.backup*` files from servers
- [ ] Update all environment files with new keys
- [ ] Restart all containers
- [ ] Test login flow end-to-end
- [ ] Test CAPTCHA flow after 3 failed attempts

### Short Term (This Week)
- [ ] Clean git history with BFG
- [ ] Update .gitignore to prevent future leaks
- [ ] Migrate to environment variable references in docker-compose
- [ ] Generate strong database passwords
- [ ] Document secret rotation procedures
- [ ] Create secret management guidelines

### Medium Term (This Month)
- [ ] Implement Docker secrets for production
- [ ] Fix UnlockController::getUnlockStatus()
- [ ] Add token validation to unlock endpoint
- [ ] Set up automated secret rotation
- [ ] Add secrets scanning to CI/CD
- [ ] Document incident response for leaked secrets

### Long Term (Next Quarter)
- [ ] Implement HashiCorp Vault
- [ ] Add automated security scanning
- [ ] Set up secret expiration alerts
- [ ] Create secure secret sharing system
- [ ] Add security awareness training

---

## 🎯 SECURITY SCORE BREAKDOWN

### Current State: 3/10
- ❌ Secrets in version control
- ❌ Weak database passwords
- ❌ Missing required environment variables
- ✅ Authentication implemented (good!)
- ✅ Rate limiting implemented
- ✅ Account lock mechanism
- ⚠️ CAPTCHA implemented but broken in prod

### After Immediate Actions: 7/10
- ✅ Secrets rotated and secured
- ✅ Environment variables properly managed
- ✅ CAPTCHA functional
- ✅ Git history cleaned
- ⚠️ Still using .env files (acceptable for now)
- ⚠️ No automated secret rotation

### After All Phases: 9/10
- ✅ Enterprise secret management
- ✅ Automated rotation
- ✅ Secrets scanning in CI/CD
- ✅ Comprehensive monitoring
- ✅ Incident response plan

---

**Document Status:** ✅ COMPLETE
**Next Review:** After Phase 0 completion
**Severity:** 🔴 IMMEDIATE ACTION REQUIRED
