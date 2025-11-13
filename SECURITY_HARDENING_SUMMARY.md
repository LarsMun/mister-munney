# Security Hardening Summary - Quick Wins

**Date:** 2025-11-13
**Status:** Phase 1 Complete (Quick Wins)

## What Was Done

### 1. ✅ CORS Configuration Fixed (CRITICAL)
**Problem:** API allowed requests from ANY website (`allow_origin: ['*']`)
**Risk:** Malicious websites could steal user data
**Solution:**
- Updated `backend/config/packages/nelmio_cors.yaml` to use environment variable
- Set `CORS_ALLOW_ORIGIN` to `https://munney.munne.me` only
- Added `allow_credentials: true` for cookie/auth support
- **Files Changed:**
  - `backend/config/packages/nelmio_cors.yaml`
  - `docker-compose.prod.yml` (added CORS_ALLOW_ORIGIN env var)
  - `backend/.env`

**Impact:** Only your production domain can now access the API. This blocks cross-site data theft.

---

### 2. ✅ API Keys and Secrets Documentation (CRITICAL)
**Problem:** Secrets were undocumented and management unclear
**Risk:** Difficult to rotate secrets, unclear what needs protection
**Solution:**
- Created `.env.example` files (root and backend) with template values
- Created `SECURITY_NOTES.md` with comprehensive secret management guide
- Verified `.env` files are properly git ignored
- **Files Changed:**
  - `.env.example` (created)
  - `backend/.env.example` (created)
  - `SECURITY_NOTES.md` (created)

**Current Secrets:**
- MySQL passwords (stored in `/srv/munney-prod/.env`)
- OpenAI API key (⚠️ **SHOULD BE ROTATED** - exposed during this session)
- JWT keys and passphrase
- Symfony APP_SECRET

**Action Required:** Rotate OpenAI API key immediately
1. Generate new key at https://platform.openai.com/api-keys
2. Update both `.env` files
3. Revoke old key
4. Restart backend: `docker restart munney-backend-prod`

---

### 3. ✅ Rate Limiting Implemented
**Problem:** No protection against brute force or DoS attacks
**Risk:** Unlimited login attempts, API abuse
**Solution:**
- Installed `symfony/rate-limiter` package
- Created rate limiter configuration with 3 tiers:
  - **API endpoints:** 100 requests/minute per IP
  - **Auth endpoints:** 5 attempts/15 minutes per IP (brute force protection)
  - **Bulk/import:** 10 requests/minute per IP
- Implemented `RateLimitSubscriber` event subscriber
- **Files Changed:**
  - `backend/config/packages/rate_limiter.yaml` (created)
  - `backend/src/Security/EventSubscriber/RateLimitSubscriber.php` (created)
  - `backend/config/services.yaml`
  - `backend/composer.json` (symfony/rate-limiter added)

**Status:** ⚠️ Configured but needs verification. The rate limiter service registration needs testing.

**To Verify After Deployment:**
```bash
# Test rate limiting on auth endpoint
for i in {1..6}; do
  curl -X POST https://munney.munne.me/api/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@test.com","password":"wrong"}'
done
# Should return 429 Too Many Requests on 6th attempt
```

---

## Security Status

### Before Quick Wins
- 🔴 CORS: Open to all origins (*)
- 🔴 Secrets: Undocumented, unclear management
- 🔴 Rate Limiting: None

### After Quick Wins
- ✅ CORS: Restricted to production domain only
- ✅ Secrets: Documented with rotation procedures
- ⚠️ Rate Limiting: Configured (needs verification)

---

## Deployment Steps

### To Apply These Changes:

1. **Commit changes to git**
   ```bash
   cd /srv/munney-prod
   git add .
   git commit -m "Security: Fix CORS, document secrets, add rate limiting"
   git push origin main
   ```

2. **Restart backend container** (to load new environment variables)
   ```bash
   docker restart munney-backend-prod
   ```

3. **Verify CORS is working**
   ```bash
   # Should return Access-Control-Allow-Origin: https://munney.munne.me
   curl -I -H "Origin: https://munney.munne.me" \
     https://munney.munne.me/api/feature-flags | grep -i access-control

   # Should NOT return Access-Control-Allow-Origin or reject
   curl -I -H "Origin: https://evil.com" \
     https://munney.munne.me/api/feature-flags | grep -i access-control
   ```

4. **Test application still works**
   - Visit https://munney.munne.me
   - Log in (if applicable)
   - Check dashboard loads
   - Check browser console for CORS errors

5. **Rotate OpenAI API Key** (IMPORTANT)
   - Get new key from OpenAI dashboard
   - Update `.env` and `backend/.env`
   - Restart backend: `docker restart munney-backend-prod`
   - Revoke old key on OpenAI platform

---

## Next Steps (Future Hardening)

### High Priority (1-2 hours each)
- **Content Security Policy (CSP) Headers** - Prevent XSS attacks
- **Move File Uploads Outside Public Directory** - Protect external payment receipts
- **Verify Rate Limiting Works** - Test all rate limiter tiers

### Medium Priority (2-4 hours each)
- **Audit Logging** - Track sensitive operations
- **Security Headers Enhancement** - Add Referrer-Policy, Permissions-Policy
- **Automated Security Scanning** - Set up composer audit in CI/CD

### Low Priority
- **Redis for Rate Limiting** - Better performance than filesystem cache
- **Fail2Ban Integration** - Ban IPs with repeated violations
- **Security Monitoring** - Set up alerts for suspicious activity

---

## Files Created/Modified

### Created:
- `.env.example` - Root environment template
- `backend/.env.example` - Backend environment template
- `SECURITY_NOTES.md` - Secret management guide
- `backend/config/packages/rate_limiter.yaml` - Rate limiter config
- `backend/src/Security/EventSubscriber/RateLimitSubscriber.php` - Rate limiting logic
- `SECURITY_HARDENING_SUMMARY.md` - This file

### Modified:
- `backend/config/packages/nelmio_cors.yaml` - CORS restriction
- `backend/.env` - Updated CORS origin
- `docker-compose.prod.yml` - Added CORS_ALLOW_ORIGIN env var
- `backend/config/services.yaml` - Rate limiter service comments
- `backend/composer.json` + `composer.lock` - Added symfony/rate-limiter

---

## Testing Checklist

After deployment, verify:
- [ ] Backend container restarts successfully
- [ ] Frontend loads without CORS errors
- [ ] API requests from frontend work
- [ ] API requests from other domains are blocked (test with curl)
- [ ] Rate limiting works (test login endpoint with multiple attempts)
- [ ] OpenAI API key rotated and old key revoked
- [ ] No errors in container logs: `docker logs munney-backend-prod`

---

## Rollback Plan

If something breaks:

1. **CORS issues:**
   ```bash
   docker exec munney-backend-prod sh -c \
     "echo 'CORS_ALLOW_ORIGIN=\"*\"' >> /var/www/html/.env"
   docker restart munney-backend-prod
   ```

2. **Rate limiting issues:**
   - Rename/delete `backend/config/packages/rate_limiter.yaml`
   - Restart backend
   - Remove rate limiter subscriber from `services.yaml`

3. **Full rollback:**
   ```bash
   cd /srv/munney-prod
   git revert HEAD
   docker restart munney-backend-prod
   ```

---

## Contact & Support

- Security issues: See `/srv/munney-prod/SECURITY_NOTES.md`
- Deployment docs: `/srv/munney-prod/PRODUCTION_DEPLOYMENT_CHECKLIST.md`
- Code documentation: `/srv/munney-prod/CLAUDE.md`

**Last Updated:** 2025-11-13
**Applied By:** Claude Code Security Audit
