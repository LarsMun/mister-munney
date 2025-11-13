# Security Implementation Progress

**Started:** 2025-11-13
**Goal:** Implement Phase 1 security hardening features
**Estimated Time:** 5-6 hours

---

## Feature 1: Login-Specific Rate Limiter ⏳ IN PROGRESS

**Goal:** Limit login attempts to 5 per 5 minutes per IP address

**Status:** 🟡 In Progress
**Started:** 2025-11-13
**Completed:** -
**Time Spent:** -

### Tasks:
- [ ] Add login rate limiter configuration to `rate_limiter.yaml`
- [ ] Create `LoginRateLimitListener` event listener
- [ ] Wire up listener in `services.yaml`
- [ ] Test with multiple failed login attempts
- [ ] Verify rate limit headers in response
- [ ] Update documentation

### Implementation Details:
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

### Files to Create/Modify:
- `backend/config/packages/rate_limiter.yaml` (modify)
- `backend/src/Security/EventListener/LoginRateLimitListener.php` (create)
- `backend/config/services.yaml` (modify)

### Testing Commands:
```bash
# Test login rate limiting (should fail after 5 attempts)
for i in {1..6}; do
  curl -X POST http://localhost:8787/api/login \
    -H "Content-Type: application/json" \
    -d '{"email":"wrong@test.com","password":"wrong"}' \
    -w "\nAttempt $i: %{http_code}\n"
done
```

### Success Criteria:
- ✅ 6th login attempt within 5 minutes returns 429 (Too Many Requests)
- ✅ Rate limit headers present: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`
- ✅ Clear error message: "Too many login attempts. Please try again in X minutes."
- ✅ Counter resets after 5 minutes

### Notes:
- Uses sliding window (more accurate than fixed window)
- Independent from global API rate limiter
- Tracks by IP address (same as global limiter)

---

## Feature 2: Failed Login Tracking + Email Unlock ⏸️ PENDING

**Goal:** Lock account after 5 failed attempts, send unlock email

**Status:** ⚪ Not Started
**Started:** -
**Completed:** -
**Time Spent:** -

### Tasks:
- [ ] Create `LoginAttempt` entity
- [ ] Create migration for `login_attempts` table
- [ ] Create `LoginAttemptService` to track attempts
- [ ] Create `AccountLockService` to handle locking/unlocking
- [ ] Generate unlock tokens (cryptographically secure)
- [ ] Create unlock email template
- [ ] Create `/api/unlock` endpoint
- [ ] Create `UnlockController`
- [ ] Integrate with login flow (check if locked before authenticating)
- [ ] Add email sending (use Symfony Mailer)
- [ ] Frontend: Show "Account locked" message with email sent notification
- [ ] Test full flow: 5 fails → email → unlock → login

### Database Schema:
```sql
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    attempted_at DATETIME NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    success BOOLEAN DEFAULT FALSE,
    INDEX idx_email_time (email, attempted_at)
);

-- Add to users table:
ALTER TABLE users ADD COLUMN is_locked BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN locked_at DATETIME DEFAULT NULL;
ALTER TABLE users ADD COLUMN unlock_token VARCHAR(64) DEFAULT NULL;
ALTER TABLE users ADD COLUMN unlock_token_expires_at DATETIME DEFAULT NULL;
```

### Files to Create/Modify:
- `backend/src/Security/Entity/LoginAttempt.php` (create)
- `backend/src/Security/Service/LoginAttemptService.php` (create)
- `backend/src/Security/Service/AccountLockService.php` (create)
- `backend/src/Security/Controller/UnlockController.php` (create)
- `backend/src/Security/EventListener/LoginAttemptListener.php` (create)
- `backend/src/User/Entity/User.php` (modify - add lock fields)
- `backend/migrations/VersionXXXXXXXXXXXXXX.php` (create)
- `backend/templates/emails/account_locked.html.twig` (create)
- `frontend/src/components/AccountLockedMessage.tsx` (create)

### Email Template:
```
Subject: Your Munney account was locked

Hi,

Your account was locked after 5 failed login attempts from IP: XXX.XXX.XXX.XXX

If this was you, click the link below to unlock your account:
https://munney.munne.me/unlock?token=XXXXX

This link expires in 1 hour.

If this wasn't you, someone may be trying to access your account.
Consider changing your password after unlocking.

- Munney Security Team
```

### Success Criteria:
- ✅ 5 failed attempts locks the account
- ✅ Locked account cannot login (even with correct password)
- ✅ Email sent immediately upon lock
- ✅ Unlock link works and unlocks account
- ✅ Unlock token expires after 1 hour
- ✅ Failed attempts counter cleared after unlock
- ✅ Successful login clears failed attempts counter
- ✅ Frontend shows clear "Account locked" message

### Configuration:
```env
# .env
MAILER_DSN=smtp://user:pass@smtp.gmail.com:587
MAIL_FROM_ADDRESS=noreply@munney.munne.me
MAIL_FROM_NAME="Munney Security"
```

### Notes:
- Need to configure Symfony Mailer for email sending
- Consider adding "resend unlock email" button
- Log all lock/unlock events in security audit log (future feature)

---

## Feature 3: CAPTCHA After 3 Failed Attempts ⏸️ PENDING

**Goal:** Show CAPTCHA challenge after 3 failed login attempts (before full lock at 5)

**Status:** ⚪ Not Started
**Started:** -
**Completed:** -
**Time Spent:** -

### Tasks:
- [ ] Choose CAPTCHA provider (hCaptcha recommended - privacy-focused, free)
- [ ] Sign up for hCaptcha and get site key + secret
- [ ] Add CAPTCHA verification to login flow
- [ ] Frontend: Show CAPTCHA widget after 3 failed attempts
- [ ] Backend: Verify CAPTCHA token before authentication
- [ ] Store CAPTCHA requirement in session/cache
- [ ] Test: Attempt 3 fails → CAPTCHA shows → solve → can login

### Implementation Details:

**hCaptcha vs reCAPTCHA:**
- ✅ hCaptcha: Privacy-focused, GDPR compliant, no Google tracking
- ❌ reCAPTCHA: Google-owned, tracks users, privacy concerns

**Decision:** Use hCaptcha

### Files to Create/Modify:
- `backend/src/Security/Service/CaptchaService.php` (create)
- `backend/src/Security/EventListener/LoginCaptchaListener.php` (create)
- `frontend/src/components/CaptchaWidget.tsx` (create)
- `frontend/src/components/AuthScreen.tsx` (modify)
- `backend/config/services.yaml` (add CAPTCHA config)

### Frontend Integration:
```typescript
// After 3 failed attempts, show CAPTCHA
{needsCaptcha && (
  <div className="mb-4">
    <HCaptcha
      sitekey={HCAPTCHA_SITE_KEY}
      onVerify={(token) => setCaptchaToken(token)}
    />
  </div>
)}
```

### Backend Verification:
```php
// Verify CAPTCHA token before authentication
if ($this->captchaService->needsCaptcha($email)) {
    $captchaToken = $data['captchaToken'] ?? null;
    if (!$this->captchaService->verify($captchaToken, $clientIp)) {
        return $this->json(['error' => 'CAPTCHA verification failed'], 400);
    }
}
```

### Configuration:
```env
# .env
HCAPTCHA_SITE_KEY=your_site_key_here
HCAPTCHA_SECRET_KEY=your_secret_key_here
```

### Files to Create/Modify:
- Install: `npm install @hcaptcha/react-hcaptcha` (frontend)
- Install: `composer require symfony/http-client` (already installed)

### Success Criteria:
- ✅ CAPTCHA appears after 3 failed login attempts
- ✅ Cannot login without solving CAPTCHA (4th and 5th attempts)
- ✅ CAPTCHA requirement cleared after successful login
- ✅ CAPTCHA requirement cleared after account unlock
- ✅ Invalid CAPTCHA token rejected with clear error
- ✅ CAPTCHA widget responsive on mobile

### Notes:
- CAPTCHA requirement stored per email (in cache, expires after 1 hour)
- Rate limiter still applies (5 attempts per 5 minutes)
- CAPTCHA adds extra layer before account lock
- Consider showing CAPTCHA on unlock page too (prevent automated unlock attempts)

---

## Testing Checklist

### After All Features Complete:

**Scenario 1: Normal Login**
- [ ] User logs in successfully on first try
- [ ] No CAPTCHA shown
- [ ] No rate limit triggered

**Scenario 2: 3 Failed Attempts**
- [ ] User fails 3 times
- [ ] CAPTCHA appears on 4th attempt
- [ ] User solves CAPTCHA and logs in successfully
- [ ] Failed attempts counter cleared

**Scenario 3: 5 Failed Attempts**
- [ ] User fails 5 times (even with CAPTCHA)
- [ ] Account locked
- [ ] Email sent with unlock link
- [ ] Cannot login even with correct password
- [ ] Unlock link works
- [ ] After unlock, can login successfully

**Scenario 4: Rate Limiting**
- [ ] User attempts 6 logins within 5 minutes
- [ ] 6th attempt returns 429 Too Many Requests
- [ ] Rate limit headers present
- [ ] After 5 minutes, can attempt again

**Scenario 5: Token Expiry**
- [ ] Unlock token expires after 1 hour
- [ ] Expired token shows error message
- [ ] User must request new unlock email

---

## Deployment Checklist

### Before Deploying to Production:

**Configuration:**
- [ ] Add `HCAPTCHA_SITE_KEY` to production `.env`
- [ ] Add `HCAPTCHA_SECRET_KEY` to production `.env`
- [ ] Configure `MAILER_DSN` for production email
- [ ] Test email sending works on production

**Database:**
- [ ] Run migrations to create `login_attempts` table
- [ ] Add lock fields to `users` table
- [ ] Verify indexes created correctly

**Monitoring:**
- [ ] Set up alerts for high failed login attempts
- [ ] Monitor locked accounts (admin dashboard)
- [ ] Track CAPTCHA solve rate

**Documentation:**
- [ ] Update user docs with account lock info
- [ ] Document unlock process
- [ ] Add troubleshooting guide

---

## Time Tracking

| Feature | Estimated | Actual | Notes |
|---------|-----------|--------|-------|
| Login Rate Limiter | 30 min | - | - |
| Failed Login + Email | 3-4 hours | - | - |
| CAPTCHA | 1-2 hours | - | - |
| **TOTAL** | **5-6 hours** | **-** | - |

---

## Issues & Blockers

_None yet_

---

## Decisions Made

1. **Rate limiter type:** Sliding window (more accurate than fixed window)
2. **CAPTCHA provider:** hCaptcha (privacy-focused, GDPR compliant)
3. **Unlock method:** Email with time-limited token (better than time-based lockout)
4. **Lock threshold:** 5 failed attempts (industry standard)
5. **CAPTCHA threshold:** 3 failed attempts (before lock)

---

## Next Steps After Phase 1

1. Two-Factor Authentication (TOTP)
2. Password reset flow
3. Security audit logging
4. Session management

---

## Questions & Notes

- Should we add a "Report suspicious activity" button on the unlock email?
- Consider adding admin override to manually unlock accounts
- Should we rate limit the unlock endpoint too?
- What happens if email fails to send? (Add to failed jobs queue?)
