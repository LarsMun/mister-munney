# Security Hardening Checklist - Munney

## Current Security Status ✅

**Already Implemented:**
- ✅ JWT Authentication (Argon2id password hashing)
- ✅ HTTPS with HSTS headers (Strict-Transport-Security)
- ✅ CORS restrictions (environment-specific, no wildcards)
- ✅ Global rate limiting (300 requests/minute per IP)
- ✅ Registration disabled (invite-only)
- ✅ Secure password hashing (Argon2id with 64MB memory cost)
- ✅ Environment-based configuration (secrets not in git)
- ✅ Docker container isolation
- ✅ Database credentials secured

## Priority 1: Critical (Implement ASAP) 🔴

### 1. Failed Login Attempt Tracking & Account Lockout
**Risk:** Brute force attacks on user accounts
**Current State:** ❌ Not implemented
**Recommendation:**
- Track failed login attempts per email address
- Lock account after 5 failed attempts within 15 minutes
- Temporary lockout: 15-30 minutes (configurable)
- Clear failed attempts counter on successful login
- Store in database (new `login_attempts` table or add to User entity)

**Implementation Complexity:** Medium (2-3 hours)
```sql
-- New table needed
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    attempted_at DATETIME NOT NULL,
    ip_address VARCHAR(45),
    INDEX idx_email_time (email, attempted_at)
);
```

### 2. Two-Factor Authentication (2FA/TOTP)
**Risk:** Compromised passwords lead to account takeover
**Current State:** ❌ Not implemented
**Recommendation:**
- TOTP-based 2FA (compatible with Google Authenticator, Authy)
- Optional for users initially, make mandatory later
- Backup codes for account recovery
- QR code generation for easy setup

**Implementation Complexity:** High (8-10 hours)
- Library: `scheb/2fa-bundle` or `spomky-labs/otphp`
- New fields in User entity: `totpSecret`, `isTwoFactorEnabled`, `backupCodes`
- Frontend: QR code display, 6-digit code input
- Recovery flow for lost 2FA device

### 3. Login-Specific Rate Limiting
**Risk:** Global rate limit (300/min) is too permissive for login endpoint
**Current State:** ⚠️ Partial (global rate limit only)
**Recommendation:**
- Separate rate limiter for `/api/login`: **5 attempts per 5 minutes per IP**
- Stricter than global limit
- Return clear error message after limit reached

**Implementation Complexity:** Low (30 minutes)
```yaml
# backend/config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        api:
            policy: 'fixed_window'
            limit: 300
            interval: '1 minute'

        # NEW: Login-specific limiter
        login:
            policy: 'sliding_window'
            limit: 5
            interval: '5 minutes'
```

## Priority 2: High (Implement Soon) 🟠

### 4. Email Verification
**Risk:** Fake accounts, no way to contact users
**Current State:** ❌ Not implemented (registration disabled anyway)
**Recommendation:**
- When registration is re-enabled: require email verification
- Send verification token via email
- Users cannot access app until verified
- Resend verification email option

**Implementation Complexity:** Medium (4-5 hours)

### 5. Password Reset Flow
**Risk:** Users locked out if they forget password (no recovery mechanism)
**Current State:** ❌ Not implemented
**Recommendation:**
- "Forgot password?" link on login screen
- Send time-limited reset token via email (expires in 1 hour)
- Secure token generation (cryptographically random)
- Force logout all sessions on password reset

**Implementation Complexity:** Medium (3-4 hours)

### 6. Password Complexity Requirements
**Risk:** Weak passwords are easier to crack
**Current State:** ⚠️ Partial (minimum 8 characters only)
**Recommendation:**
- Enforce: Minimum 12 characters (or 8 with complexity)
- Require: At least 1 uppercase, 1 lowercase, 1 number, 1 special character
- Block common passwords (use `have-i-been-pwned` API or local list)
- Password strength meter on frontend

**Implementation Complexity:** Low (1-2 hours)

### 7. Session Management & Device Tracking
**Risk:** No visibility into active sessions, can't revoke compromised sessions
**Current State:** ⚠️ JWT tokens (stateless, can't revoke)
**Recommendation:**
- Store active JWT tokens in database (makes them revocable)
- Track device/browser info (User-Agent)
- "Active Sessions" page: show all logged-in devices
- "Log out all devices" button
- Automatic session expiry (current: JWT expiry, but not trackable)

**Implementation Complexity:** High (6-8 hours)

## Priority 3: Medium (Nice to Have) 🟡

### 8. Security Audit Logging
**Risk:** No visibility into security events
**Current State:** ❌ Not implemented
**Recommendation:**
- Log security events: Failed logins, successful logins, password changes, 2FA changes
- Store in database with timestamp, IP, user agent
- Admin interface to view audit logs
- Retention policy (delete logs older than 1 year)

**Implementation Complexity:** Medium (4-5 hours)

### 9. Account Recovery Questions
**Risk:** Users lose access to email and 2FA device
**Current State:** ❌ Not implemented
**Recommendation:**
- Optional security questions during signup
- Backup recovery method if both email and 2FA are lost
- Manual admin intervention as last resort

**Implementation Complexity:** Medium (3-4 hours)

### 10. IP Whitelisting (Optional for Admin Users)
**Risk:** Admin accounts compromised from untrusted locations
**Current State:** ❌ Not implemented
**Recommendation:**
- Optional per-user IP whitelist
- Admin users can restrict their account to specific IPs/ranges
- Useful if you only access from home/office

**Implementation Complexity:** Low (2 hours)

### 11. Suspicious Activity Detection
**Risk:** Compromised accounts go unnoticed
**Current State:** ❌ Not implemented
**Recommendation:**
- Detect unusual login patterns:
  - Login from new location/country
  - Login from new device
  - Login at unusual time
- Email user when suspicious activity detected
- Require email confirmation for suspicious logins

**Implementation Complexity:** High (6-8 hours)

### 12. CAPTCHA on Login (After Failed Attempts)
**Risk:** Automated brute force attacks
**Current State:** ❌ Not implemented
**Recommendation:**
- Show CAPTCHA after 3 failed login attempts
- Use hCaptcha or reCAPTCHA
- Prevents automated attacks while allowing legitimate users

**Implementation Complexity:** Low (1-2 hours)

## Priority 4: Low (Future Enhancement) 🟢

### 13. Hardware Security Keys (WebAuthn/FIDO2)
**Risk:** TOTP codes can be phished
**Current State:** ❌ Not implemented
**Recommendation:**
- Support YubiKey, Titan Key, etc.
- More secure than TOTP
- Phishing-resistant

**Implementation Complexity:** Very High (10+ hours)

### 14. Biometric Authentication
**Risk:** N/A (enhancement)
**Current State:** ❌ Not implemented
**Recommendation:**
- Fingerprint/Face ID on mobile
- TouchID on macOS
- Requires WebAuthn implementation

**Implementation Complexity:** Very High (10+ hours)

---

## Recommended Implementation Order

### Phase 1: Essential Security (1-2 weeks)
1. ✅ **Login-specific rate limiting** (5 attempts/5 min) - 30 min
2. ✅ **Failed login tracking & account lockout** - 2-3 hours
3. ✅ **Password complexity requirements** - 1-2 hours
4. ✅ **CAPTCHA after failed attempts** - 1-2 hours

**Total:** ~1 day of work

### Phase 2: User Protection (2-3 weeks)
1. ✅ **Two-Factor Authentication (TOTP)** - 8-10 hours
2. ✅ **Password reset flow** - 3-4 hours
3. ✅ **Security audit logging** - 4-5 hours

**Total:** ~2-3 days of work

### Phase 3: Advanced Features (1-2 months)
1. ✅ **Session management & device tracking** - 6-8 hours
2. ✅ **Email verification** (when registration re-enabled) - 4-5 hours
3. ✅ **Suspicious activity detection** - 6-8 hours

**Total:** ~2-3 days of work

---

## Quick Wins (Implement Today)

These can be done immediately with minimal code:

### 1. Add Login Rate Limiter (30 minutes)
```yaml
# backend/config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        login:
            policy: 'sliding_window'
            limit: 5
            interval: '5 minutes'
```

```php
// Add to a LoginListener
$limiter = $this->loginLimiter->create($clientIp);
if (!$limiter->consume(1)->isAccepted()) {
    throw new TooManyRequestsHttpException(300, 'Too many login attempts. Try again in 5 minutes.');
}
```

### 2. Improve Password Validation (1 hour)
```php
// backend/src/User/Validator/StrongPassword.php
#[Attribute]
class StrongPassword extends Constraint
{
    public string $message = 'Password must be at least 12 characters and contain uppercase, lowercase, number, and special character.';
}

// Apply to User entity
#[StrongPassword]
private string $password;
```

### 3. Add Security Headers (Already Done via Traefik)
- ✅ Strict-Transport-Security (HSTS)
- ✅ X-Content-Type-Options: nosniff
- ✅ X-Frame-Options: SAMEORIGIN

---

## Testing Checklist

After implementing security features, test:

- [ ] Cannot login with wrong password 5 times without lockout
- [ ] Locked account cannot login until timeout expires
- [ ] 2FA code required after password (when enabled)
- [ ] Invalid 2FA code rejects login
- [ ] Password reset link works and expires after 1 hour
- [ ] Cannot reuse old password reset tokens
- [ ] Rate limiter blocks after 5 login attempts
- [ ] CAPTCHA appears after 3 failed attempts
- [ ] Weak passwords rejected during password change
- [ ] Audit log records security events
- [ ] "Log out all devices" invalidates all sessions

---

## Security Monitoring

After hardening, monitor:

1. **Failed login attempts** (daily)
2. **Account lockouts** (investigate if spike)
3. **2FA adoption rate** (encourage users to enable)
4. **Password reset requests** (unusual spikes?)
5. **Suspicious login detections** (new locations)

---

## Notes

- All security features should be **configurable via environment variables**
- Consider adding a **security dashboard** for admins to monitor threats
- Keep libraries updated (Symfony, JWT bundle, etc.)
- Regular security audits every 6 months
- Document security features in user-facing help docs

---

## Compliance Considerations

If storing financial data:
- ✅ **PCI-DSS:** Not applicable (not storing card data)
- ✅ **GDPR:** User data encrypted, right to deletion needed
- ✅ **Data residency:** Database hosted where?
- ⚠️ **Backup encryption:** Are backups encrypted?

---

## Estimated Total Time Investment

| Priority | Features | Time |
|----------|----------|------|
| Priority 1 (Critical) | 3 features | ~1 day |
| Priority 2 (High) | 7 features | ~3-4 days |
| Priority 3 (Medium) | 5 features | ~3-4 days |
| Priority 4 (Low) | 2 features | ~2-3 days |
| **TOTAL** | **17 features** | **~2 weeks full-time** |

**Recommendation:** Start with Priority 1 (1 day of work) to get the most critical protections in place immediately.
