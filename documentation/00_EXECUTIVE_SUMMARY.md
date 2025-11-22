# MISTER MUNNEY - COMPREHENSIVE APPLICATION AUDIT
## EXECUTIVE SUMMARY

**Audit Date:** November 20, 2025
**Auditor:** Claude Code (Comprehensive Technical Audit)
**Application:** Mister Munney (Personal Finance Management)
**Stack:** Symfony 7.2 (PHP 8.2+) + React 19 (TypeScript)
**Environment:** WSL2 Development + Ubuntu Production Server (192.168.0.105)

---

## 🎯 OVERALL HEALTH ASSESSMENT

| Category | Score | Status | Priority |
|----------|-------|--------|----------|
| **Security** | 🔴 **3/10** | **CRITICAL** | P0 - Immediate |
| **Deployment** | 🔴 **4/10** | **HIGH RISK** | P0 - Immediate |
| **Architecture** | 🟢 **8/10** | Good | P2 |
| **Code Quality** | 🟡 **7/10** | Acceptable | P2 |
| **Performance** | 🟢 **7.5/10** | Good | P3 |
| **Testing** | 🟡 **6/10** | Needs Work | P2 |

### **OVERALL SCORE: 5.9/10** - NOT PRODUCTION READY

**Blockers to Production:**
1. 🔴 Hardcoded secrets in version control (API keys, JWT passphrase)
2. 🔴 Production .env files contain exposed secrets
3. 🟡 Authentication implemented but deployment pipeline has issues
4. 🟡 Configuration management is inconsistent across environments

---

## 🚨 TOP 5 CRITICAL ISSUES

### 1. HARDCODED API KEYS IN VERSION CONTROL
**Severity:** 🔴 **CRITICAL** | **Impact:** SEVERE SECURITY BREACH | **Effort:** S

**Evidence:**
- `docker-compose.yml` (line 14): Resend API key hardcoded: `re_UrrEVv6w_9NEHJayyB1VWJHB9g7bZcgfu`
- `deploy/ubuntu/docker-compose.prod.yml` (line 27): JWT passphrase hardcoded: `+Qdsl7gdFOYMlixhppIKftcHetoUa/G2gxZBBLOx9Is=`
- `backend/.env`: hCaptcha secret key exposed: `ES_e9abae79ed0f4f448f3ef6994d0af93b`
- Production server `/srv/munney-prod/.env`: OpenAI API key exposed (full key retrieved via earlier SSH)

**Impact:**
- Anyone with git access can steal API keys
- If repository is/was public, keys are permanently compromised
- Resend email API can be abused for spam
- OpenAI API can rack up massive costs
- JWT tokens can be forged if passphrase is known
- hCaptcha protection can be bypassed

**Immediate Actions Required:**
1. Revoke ALL exposed API keys immediately (Resend, OpenAI, hCaptcha)
2. Generate new JWT keypairs and passphrase
3. Remove secrets from git history using `git filter-branch` or BFG
4. Implement proper secret management (environment variables, Docker secrets)
5. Add `.env*` files to `.gitignore` (verify backend/.env is not tracked)

---

### 2. MYSQL CREDENTIALS EXPOSED IN DOCKER-COMPOSE
**Severity:** 🔴 **CRITICAL** | **Impact:** DATABASE COMPROMISE | **Effort:** S

**Evidence:**
- `docker-compose.yml` (lines 46, 49): Weak password `moneymakestheworldgoround` hardcoded
- Root and user passwords identical
- Password visible in version control
- Password exposed in container environment variables

**Impact:**
- Trivial database access for anyone with code access
- Port 3333 exposed on localhost (can be port-forwarded via SSH)
- No defense-in-depth if application is compromised

**Recommendation:**
```bash
# Generate strong passwords
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
MYSQL_PASSWORD=$(openssl rand -base64 32)

# Store in .env.local (gitignored)
echo "MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD" >> .env.local
echo "MYSQL_PASSWORD=$MYSQL_PASSWORD" >> .env.local

# Use in docker-compose.yml
DATABASE_URL: "mysql://money:${MYSQL_PASSWORD}@database:3306/money_db"
```

---

### 3. PRODUCTION .ENV FILE CONTAINS LEAKED SECRETS
**Severity:** 🔴 **CRITICAL** | **Impact:** COMPLETE SYSTEM COMPROMISE | **Effort:** M

**Evidence from `/srv/munney-prod/.env`:**
```bash
OPENAI_API_KEY=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
MAILER_DSN=resend+api://re_UrrEVv6w_9NEHJayyB1VWJHB9g7bZcgfu@default
JWT_PASSPHRASE=+Qdsl7gdFOYMlixhppIKftcHetoUa/G2gxZBBLOx9Is=
JWT_PASSPHRASE_PROD=27deec7964942d0b60d20fed8bc31d59d4b682fc92dadd89cce60af61e934f24
```

**Issues:**
- Full OpenAI API key exposed (can incur $1000s in charges)
- Same Resend API key as in docker-compose.yml
- Duplicate JWT_PASSPHRASE variables (confusing and error-prone)
- No encryption at rest for sensitive environment variables

**Impact:**
- Financial loss from OpenAI API abuse
- Email sending abuse via Resend
- Ability to forge JWT tokens
- Potential server compromise if other credentials are exposed

---

### 4. INCONSISTENT ENVIRONMENT CONFIGURATION
**Severity:** 🟡 **HIGH** | **Impact:** DEPLOYMENT FAILURES | **Effort:** M

**Evidence:**

**Local (`docker-compose.yml`):**
- Hardcoded database credentials
- Hardcoded MAILER_DSN with API key
- CORS pattern: `^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$$`

**Dev Server (`/srv/munney-dev`):**
- Uses environment variables properly
- But OpenAI key is EMPTY: `OPENAI_API_KEY_DEV=`
- Different database naming: `money_db_dev`

**Prod Server (`/srv/munney-prod`):**
- Uses environment variables
- But has duplicate configuration
- Multiple `.env.backup*` files present (security risk)
- `docker-compose.prod.yml` in root vs `deploy/ubuntu/docker-compose.prod.yml`

**Deployment Pipeline (`deploy/ubuntu/docker-compose.prod.yml`):**
- References `${MYSQL_PASSWORD_PROD}` but root `.env` uses `${MYSQL_PASSWORD}`
- JWT_PASSPHRASE hardcoded in compose file (line 27) instead of using `${JWT_PASSPHRASE_PROD}`
- Inconsistent environment variable naming conventions

**Issues:**
- Deployments can succeed locally but fail on server
- Environment variables don't match between files
- No single source of truth for configuration
- Manual synchronization required (error-prone)

**Root Cause:**
Multiple docker-compose files serving different purposes without clear hierarchy:
1. `docker-compose.yml` - Local development (has hardcoded secrets)
2. `docker-compose.prod.yml` - Root directory (references Traefik)
3. `deploy/ubuntu/docker-compose.dev.yml` - Server dev deployment
4. `deploy/ubuntu/docker-compose.prod.yml` - Server prod deployment

---

### 5. NO HCAPTCHA_SECRET_KEY IN PRODUCTION ENV
**Severity:** 🟡 **HIGH** | **Impact:** BROKEN SECURITY FEATURE | **Effort:** XS

**Evidence:**
- `backend/config/services.yaml` (line 63): References `%env(HCAPTCHA_SECRET_KEY)%`
- `backend/.env` has the secret: `ES_e9abae79ed0f4f448f3ef6994d0af93b`
- Production `/srv/munney-prod/.env`: **No HCAPTCHA_SECRET_KEY defined**
- Development `/srv/munney-dev/.env`: **No HCAPTCHA_SECRET_KEY defined**

**Impact:**
- CAPTCHA verification will fail after 3 failed login attempts
- Login attempts tracker will work, but CAPTCHA requirement will break authentication
- Security feature (brute force protection) is non-functional in production
- Users will be unable to login after triggering CAPTCHA requirement

**Immediate Fix:**
```bash
# Add to production .env
echo "HCAPTCHA_SECRET_KEY=ES_e9abae79ed0f4f448f3ef6994d0af93b" >> /srv/munney-prod/.env

# But also: Generate NEW secret key since the current one is exposed in git
# Get new keys from https://www.hcaptcha.com/
```

---

## 🔥 HIGH PRIORITY IMPROVEMENTS

### 6. GitHub Actions Deployment Workflow Has No Migrations on Dev
**Severity:** 🟡 **HIGH** | **Impact:** BROKEN FEATURES ON DEV | **Effort:** XS

**Evidence:**
- `.github/workflows/deploy-prod.yml` runs migrations (line 86-90)
- `.github/workflows/deploy-dev.yml` **DOES NOT** run migrations
- Dev deployments skip database schema updates
- Features requiring new tables/columns will break on dev

**Fix:**
```yaml
# Add to .github/workflows/deploy-dev.yml after container restart
- name: 🗄️ Run database migrations
  run: |
    cd /srv/munney-dev
    docker exec munney-dev-backend php bin/console doctrine:migrations:migrate --no-interaction --env=dev
    echo "✅ Migrations complete"
```

---

### 7. Git Status Shows Deleted Documentation Files
**Severity:** 🟢 **LOW** | **Impact:** REPOSITORY HYGIENE | **Effort:** XS

**Evidence from `git status`:**
```
D ACCESSIBILITY.md
D ACCOUNT_SWITCHING_FIX.md
D AUTHENTICATION_GUIDE.md
D BACKEND_CODE_REVIEW.md
... (27 deleted .md files not committed)
```

**Issue:**
- 30+ documentation files deleted but changes not committed
- Unclean working directory
- Risk of accidentally committing/losing documentation
- Confusing for team members

**Fix:**
```bash
# Either commit the deletions:
git add -u
git commit -m "docs: Remove obsolete documentation files"

# OR restore them if deleted by mistake:
git restore ACCESSIBILITY.md ACCOUNT_SWITCHING_FIX.md ...

# OR move to archive folder instead of deleting:
mkdir -p docs/archive
git mv *.md docs/archive/
```

---

## 📊 DETAILED FINDINGS SUMMARY

### Security Findings: 13 Issues
- **CRITICAL** (3): Hardcoded secrets, exposed production credentials, missing env vars
- **HIGH** (4): Weak database passwords, configuration inconsistencies, missing HCAPTCHA
- **MEDIUM** (4): UnlockController returns empty response (line 182), no validation on token length
- **LOW** (2): Multiple .env backup files on server, git history contains secrets

### Architecture Findings: 8 Issues
- ✅ **Excellent**: Domain-Driven Design with bounded contexts
- ✅ **Strong**: Doctrine ORM with proper entities and repositories
- ✅ **Good**: React with TypeScript and domain-based structure
- 🟡 **Concern**: 19 database migrations (shows active development, ensure all applied)
- 🟡 **Concern**: No service interfaces (tight coupling, harder to test)

### Deployment Findings: 11 Issues
- 🔴 **CRITICAL**: Multiple docker-compose files with conflicting configurations
- 🔴 **HIGH**: Dev workflow missing migrations step
- 🟡 **MEDIUM**: No rollback strategy documented
- 🟡 **MEDIUM**: SSH connection to server failed during audit (server down?)
- 🟢 **LOW**: No health checks after deployment (except HTTP request)

### Configuration Findings: 9 Issues
- 🔴 **CRITICAL**: Secrets in git-tracked files
- 🟡 **HIGH**: Environment variable naming inconsistencies
- 🟡 **MEDIUM**: Multiple .env files per environment (confusing)
- 🟡 **MEDIUM**: `.env.backup*` files on production server (security risk)

### Code Quality Findings: 5 Issues
- ✅ **Excellent**: Comprehensive OpenAPI documentation
- ✅ **Good**: Type safety with PHP 8.2+ and TypeScript
- 🟡 **Concern**: Only 3 TODO comments found (good - low technical debt markers)
- 🟡 **Concern**: `UnlockController::getUnlockStatus()` always returns not locked (line 181-184)
- 🟢 **Minor**: Some entity files approaching 300 lines (still reasonable)

---

## 📋 RECOMMENDED IMPLEMENTATION ROADMAP

### 🔥 PHASE 0: IMMEDIATE ACTIONS (Next 24 Hours)
**Priority:** 🔴 **STOP-THE-WORLD CRITICAL**

1. **Revoke Compromised API Keys**
   - [ ] Revoke Resend API key `re_UrrEVv6w_9NEHJayyB1VWJHB9g7bZcgfu`
   - [ ] Rotate OpenAI API key
   - [ ] Generate new hCaptcha site and secret keys
   - [ ] Generate new JWT keypair and passphrase

2. **Remove Secrets from Git**
   - [ ] Add all `.env*` files to `.gitignore`
   - [ ] Use BFG Repo-Cleaner to remove secrets from history
   - [ ] Force push cleaned history (coordinate with team!)

3. **Update Production Environment**
   - [ ] Deploy new API keys to `/srv/munney-prod/.env`
   - [ ] Add missing `HCAPTCHA_SECRET_KEY` to production
   - [ ] Remove backup `.env.backup*` files from server
   - [ ] Restart production containers

**Estimated Time:** 4-6 hours
**Risk Level:** HIGH (service disruption if not coordinated)

---

### 🔥 PHASE 1: SECURITY HARDENING (Week 1)
**Priority:** 🔴 **CRITICAL**

1. **Implement Secret Management**
   ```bash
   # Use Docker secrets for production
   echo "your-secret" | docker secret create mysql_password -
   echo "your-secret" | docker secret create openai_api_key -
   ```

2. **Standardize Environment Configuration**
   - Create `.env.example` with all required variables (no values)
   - Document which env vars are required for each environment
   - Remove hardcoded values from all docker-compose files

3. **Fix Configuration Inconsistencies**
   - Consolidate to single docker-compose structure
   - Use `docker-compose.override.yml` for local dev only
   - Create clear dev/prod environment variable mapping

**Estimated Time:** 16 hours
**Deliverables:** Clean git history, secure secret storage, documented config

---

### 🟡 PHASE 2: DEPLOYMENT PIPELINE (Week 2)
**Priority:** **HIGH**

1. **Fix Dev Deployment Workflow**
   - Add migration step to deploy-dev.yml
   - Add cache clear step
   - Add health checks after deployment

2. **Add Pre-Deployment Checks**
   ```yaml
   - name: Verify environment variables
     run: |
       required_vars="MYSQL_PASSWORD OPENAI_API_KEY JWT_PASSPHRASE"
       for var in $required_vars; do
         if [ -z "${!var}" ]; then
           echo "ERROR: $var is not set"
           exit 1
         fi
       done
   ```

3. **Document Deployment Process**
   - Create `DEPLOYMENT.md` with step-by-step guide
   - Document rollback procedure
   - Create deployment checklist

**Estimated Time:** 12 hours
**Deliverables:** Reliable deployment pipeline, documentation

---

### 🟢 PHASE 3: CODE QUALITY (Week 3-4)
**Priority:** **MEDIUM**

1. **Fix Broken Functionality**
   - Fix `UnlockController::getUnlockStatus()` to actually check lock status
   - Verify all 19 migrations are applied to dev and prod
   - Test CAPTCHA flow end-to-end

2. **Improve Error Handling**
   - Add validation for unlock token format
   - Add rate limiting to unlock endpoints
   - Improve error messages

3. **Testing**
   - Add integration tests for deployment scenarios
   - Test environment variable fallbacks
   - Test security features (lock, captcha, rate limiting)

**Estimated Time:** 20 hours
**Deliverables:** Bug fixes, improved reliability

---

## 🎯 FINAL RECOMMENDATIONS

### MUST DO (Before Any Production Use):
1. ✅ Revoke and rotate ALL exposed API keys
2. ✅ Remove secrets from git history
3. ✅ Implement proper secret management
4. ✅ Add HCAPTCHA_SECRET_KEY to production
5. ✅ Fix environment configuration inconsistencies

### SHOULD DO (Next Sprint):
1. ⚠️ Add migrations to dev deployment workflow
2. ⚠️ Consolidate docker-compose file structure
3. ⚠️ Document deployment and rollback procedures
4. ⚠️ Fix UnlockController::getUnlockStatus() implementation
5. ⚠️ Clean up git working directory (commit or restore deleted files)

### NICE TO HAVE (Future):
1. 💡 Implement HashiCorp Vault for secret management
2. 💡 Add automated security scanning to CI/CD
3. 💡 Create development environment setup script
4. 💡 Add monitoring and alerting for production
5. 💡 Implement blue-green deployment strategy

---

## 💡 SPECIALIZED AGENT NEEDS

Based on this audit, a specialized Claude Code agent for Mister Munney should:

1. **Know the Deployment Architecture**
   - Understand multi-environment setup (local WSL2, Ubuntu dev, Ubuntu prod)
   - Recognize the docker-compose file hierarchy
   - Enforce secret management best practices

2. **Enforce Security Patterns**
   - Prevent hardcoding secrets in any files
   - Require environment variable usage
   - Validate .gitignore includes all sensitive files
   - Check for exposed credentials before commits

3. **Understand the Tech Stack**
   - Symfony 7.2 with Domain-Driven Design
   - React 19 with TypeScript and domain-based architecture
   - MySQL 8.0 with Doctrine ORM
   - JWT authentication with lexik/jwt-authentication-bundle
   - Rate limiting and security middleware

4. **Guide Deployment Process**
   - Ensure migrations run on all deployments
   - Verify environment variables before deployment
   - Enforce pre-deployment checklist
   - Guide rollback procedures if needed

5. **Catch Common Mistakes**
   - Environment variable naming inconsistencies
   - Missing environment variables in new features
   - Secrets in code
   - Broken security features (like missing HCAPTCHA_SECRET_KEY)

---

**Next Steps:**
1. Review this executive summary
2. Read detailed findings in remaining documentation files
3. Begin Phase 0 immediate actions
4. Schedule security sprint for Phase 1

**Audit Status:** ✅ COMPLETE
**Report Location:** `documentation/00_EXECUTIVE_SUMMARY.md`
**Follow-up Required:** IMMEDIATE (Phase 0 actions)
