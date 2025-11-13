# Sync Security Changes to Dev/Local

**Date:** 2025-11-13
**Purpose:** Security hardening changes made in production need to be synced to dev and local environments

---

## 🔴 CRITICAL - Backend Code Changes (MUST Sync)

These changes are **application code** and need to be in all environments:

### 1. CORS Configuration
**File:** `backend/config/packages/nelmio_cors.yaml`
- **Changed:** Wildcard `*` replaced with environment variable `%env(CORS_ALLOW_ORIGIN)%`
- **Action:** Commit this change to git
- **Local/Dev Impact:** You'll need to set `CORS_ALLOW_ORIGIN` in your `.env` files

### 2. Rate Limiting (NEW)
**Files:**
- `backend/config/packages/rate_limiter.yaml` (NEW FILE)
- `backend/src/Security/EventSubscriber/RateLimitSubscriber.php` (NEW FILE)
- `backend/config/services.yaml` (MODIFIED - added rate limiter comments)

**Action:**
- Commit all three files to git
- Run `composer install` in dev/local after pulling

### 3. Composer Dependencies
**Files:**
- `backend/composer.json` (added symfony/rate-limiter)
- `backend/composer.lock` (updated)

**Action:**
- Commit both files to git
- Run `composer install` in dev/local after pulling

### 4. Environment Template
**File:** `backend/.env.example` (NEW FILE)

**Action:** Commit to git as documentation

---

## 🟡 OPTIONAL - Documentation (Should Sync)

These are helpful documentation files:

- `.env.example` (root) - NEW
- `SECURITY_NOTES.md` - NEW
- `SECURITY_HARDENING_SUMMARY.md` - NEW
- `deploy-security-fixes.sh` - NEW (production only, but good to have)

**Action:** Commit to git for team reference

---

## 🟢 ENVIRONMENT-SPECIFIC - Docker Compose Changes

These changes are **environment-specific** and may differ between prod/dev/local:

### Production (`docker-compose.prod.yml`)
**Changes:**
- Added `CORS_ALLOW_ORIGIN: "https://munney.munne.me"` env var
- Should be committed to git

### Production (`deploy/ubuntu/docker-compose.prod.yml`)
**Changes:**
- Added HTTP to HTTPS redirect middleware
- Added HTTP routers for both frontend and backend
- Should be committed to git

### Development (`docker-compose.yml` or dev equivalent)
**Needs similar changes:**
```yaml
backend:
  environment:
    CORS_ALLOW_ORIGIN: "http://localhost:3000"  # Dev frontend URL
```

### Local (WSL2 - `docker-compose.yml`)
**Needs similar changes:**
```yaml
backend:
  environment:
    CORS_ALLOW_ORIGIN: "http://localhost:3000"  # Local frontend URL
```

---

## 📋 Step-by-Step Sync Instructions

### For Development Server (devmunney.home.munne.me - Internal Only):

```bash
# 1. SSH to dev server
ssh lars@apollowebserv

# 2. Navigate to dev deployment
cd /srv/munney-dev  # Or wherever your dev is

# 3. Pull latest changes (after you commit/push)
git pull origin develop

# 4. Update composer dependencies
docker exec munney-dev-backend composer install

# 5. CORS is already configured in deploy/ubuntu/docker-compose.dev.yml
# No need to add to .env - docker-compose sets it automatically

# 6. Clear Symfony cache
docker exec munney-dev-backend php bin/console cache:clear

# 7. Restart backend
docker restart munney-dev-backend
```

### For Local (WSL2):

```bash
# 1. Navigate to local project
cd /path/to/munney-local

# 2. Pull latest changes
git pull origin main  # or develop

# 3. Rebuild backend container (since you don't use volumes)
docker compose build backend

# 4. Update .env with local CORS
echo 'CORS_ALLOW_ORIGIN="http://localhost:3000"' >> backend/.env

# 5. Run composer install
docker compose up -d
docker exec money-backend composer install

# 6. Clear cache
docker exec money-backend php bin/console cache:clear

# 7. Rebuild frontend container (if needed)
docker compose build frontend
docker compose up -d
```

---

## 🧪 Testing After Sync

### Verify CORS:
```bash
# Should succeed (your frontend domain)
curl -H "Origin: http://localhost:3000" http://localhost:8787/api/feature-flags

# Should fail or have no CORS headers (other domains)
curl -H "Origin: https://evil.com" http://localhost:8787/api/feature-flags
```

### Verify Rate Limiting:
```bash
# Make 6 rapid requests - should get 429 on 6th attempt
for i in {1..6}; do
  echo "Request $i:"
  curl -X POST http://localhost:8787/api/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@test.com","password":"wrong"}' \
    -w " HTTP %{http_code}\n"
  sleep 1
done
```

### Verify Backend Works:
```bash
# Should return API documentation title
docker exec money-backend curl -s http://localhost/api/doc.json | jq -r '.info.title'
# Expected: "Mr Munney API"
```

---

## 🔄 Git Commit Strategy

**Recommended approach:**

```bash
# 1. Stage backend code changes
git add backend/config/packages/nelmio_cors.yaml
git add backend/config/packages/rate_limiter.yaml
git add backend/src/Security/EventSubscriber/RateLimitSubscriber.php
git add backend/config/services.yaml
git add backend/composer.json backend/composer.lock

# 2. Stage environment templates
git add .env.example backend/.env.example

# 3. Stage documentation
git add SECURITY_NOTES.md SECURITY_HARDENING_SUMMARY.md

# 4. Stage docker compose changes
git add docker-compose.prod.yml deploy/ubuntu/docker-compose.prod.yml

# 5. Commit
git commit -m "security: Add CORS restrictions and rate limiting

- Restrict CORS to specific origins via env var
- Add 3-tier rate limiting (API, auth, bulk operations)
- Install symfony/rate-limiter package
- Add HTTP to HTTPS redirect for production
- Document secret management procedures

Fixes security issues identified in security audit"

# 6. Push to remote
git push origin main
```

---

## ⚠️ Important Notes

1. **CORS_ALLOW_ORIGIN** must be set in each environment's `.env` file:
   - Production: `https://munney.munne.me`
   - Dev: `https://devmunney.munne.me`
   - Local: `http://localhost:3000`

2. **Rate Limiter** uses filesystem cache by default (fine for single-server setups)

3. **HTTP Redirect** middleware is only needed for production (with Traefik)
   - Dev and local usually access via direct ports (8787, 3000)

4. **Composer dependencies** - The new `symfony/rate-limiter` package must be installed in all environments

---

## 🆘 Troubleshooting

### "CORS_ALLOW_ORIGIN not set" error:
```bash
# Add to backend/.env
echo 'CORS_ALLOW_ORIGIN="http://localhost:3000"' >> backend/.env
docker restart money-backend
```

### "Class RateLimiterFactory not found":
```bash
# Install composer dependencies
docker exec money-backend composer install
docker exec money-backend composer dump-autoload
docker restart money-backend
```

### "Frontend can't connect to backend":
```bash
# Check CORS is set correctly
docker exec money-backend env | grep CORS

# Should match your frontend URL
# Dev: https://devmunney.munne.me
# Local: http://localhost:3000
# Prod: https://munney.munne.me
```

---

**Last Updated:** 2025-11-13
**Status:** Ready to sync
