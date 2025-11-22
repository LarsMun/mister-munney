# IMMEDIATE ACTION PLAN
## Mister Munney - Critical Fixes Required

**Date:** November 20, 2025
**Status:** 🔴 **URGENT - ACTION REQUIRED**
**Estimated Time:** 4-6 hours
**Risk Level:** HIGH (Exposed secrets, broken features)

---

## 🚨 CRITICAL ACTIONS (DO FIRST - NEXT 2 HOURS)

### Action 1: Revoke ALL Compromised API Keys

**Priority:** 🔴 **CRITICAL** | **Time:** 30 minutes

```bash
# 1. RESEND EMAIL API
# Login to: https://resend.com/api-keys
# Revoke key: re_UrrEVv6w_9NEHJayyB1VWJHB9g7bZcgfu
# Generate new key and save to password manager
NEW_RESEND_KEY="re_YOUR_NEW_KEY_HERE"

# 2. OPENAI API
# Login to: https://platform.openai.com/api-keys
# Revoke key starting with: sk-proj-MjDnta3M52e6w4w...
# Generate new PROJECT key with spending limit ($50/month recommended)
NEW_OPENAI_KEY="sk-proj-YOUR_NEW_KEY_HERE"

# 3. HCAPTCHA
# Login to: https://www.hcaptcha.com/
# Regenerate both site key and secret key
NEW_HCAPTCHA_SITE_KEY="YOUR_NEW_SITE_KEY"
NEW_HCAPTCHA_SECRET_KEY="ES_YOUR_NEW_SECRET_KEY"

# 4. JWT KEYPAIR (run on server)
ssh lars@192.168.0.105
cd /srv/munney-prod/backend

# Backup old keys
cp config/jwt/private.pem config/jwt/private.pem.backup
cp config/jwt/public.pem config/jwt/public.pem.backup

# Generate new passphrase
NEW_JWT_PASSPHRASE=$(openssl rand -base64 32)
echo "Save this passphrase: $NEW_JWT_PASSPHRASE"

# Generate new keypair
rm config/jwt/*.pem
openssl genpkey -algorithm RSA -out config/jwt/private.pem \
  -aes256 -pass pass:"$NEW_JWT_PASSPHRASE" -pkeyopt rsa_keygen_bits:4096
openssl rsa -pubout -in config/jwt/private.pem \
  -out config/jwt/public.pem -passin pass:"$NEW_JWT_PASSPHRASE"
chmod 644 config/jwt/*.pem

# Store passphrase securely
echo "JWT_PASSPHRASE_PROD=$NEW_JWT_PASSPHRASE" >> ../.env
```

**⚠️ WARNING:** After rotating keys, ALL existing JWT tokens will be invalid. Users will need to log in again.

---

### Action 2: Update Production Environment Variables

**Priority:** 🔴 **CRITICAL** | **Time:** 15 minutes

```bash
# SSH to production server
ssh lars@192.168.0.105

# Backup current .env
cd /srv/munney-prod
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

# Edit .env with new keys
nano .env

# Update these lines:
# OPENAI_API_KEY=sk-proj-YOUR_NEW_KEY_HERE
# MAILER_DSN=resend+api://re_YOUR_NEW_KEY_HERE@default
# HCAPTCHA_SECRET_KEY=ES_YOUR_NEW_SECRET_KEY
# HCAPTCHA_SITE_KEY=YOUR_NEW_SITE_KEY  # Add this line if missing
# JWT_PASSPHRASE_PROD=YOUR_NEW_PASSPHRASE

# Save and exit (Ctrl+X, Y, Enter)

# Verify variables are set
grep -E "(OPENAI|MAILER|HCAPTCHA|JWT_PASSPHRASE)" .env

# Restart containers
docker compose -f deploy/ubuntu/docker-compose.prod.yml restart

# Wait for containers to start
sleep 15

# Verify containers are running
docker ps --filter 'label=project=munney' --filter 'label=environment=production'

# Check backend logs for errors
docker logs munney-prod-backend --tail 50

# Test API is responding
curl -f https://munney.munne.me/api || echo "ERROR: API not responding"
```

**Verification Steps:**
1. API responds: `curl https://munney.munne.me/api`
2. Frontend loads: Visit https://munney.munne.me
3. Login works
4. Test CAPTCHA (fail login 3 times, should show CAPTCHA)

---

### Action 3: Update Dev Environment Variables

**Priority:** 🔴 **HIGH** | **Time:** 10 minutes

```bash
# SSH to server
ssh lars@192.168.0.105

# Update dev environment
cd /srv/munney-dev

# Backup
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

# Edit .env
nano .env

# Add/Update:
# HCAPTCHA_SECRET_KEY=ES_YOUR_NEW_SECRET_KEY
# HCAPTCHA_SITE_KEY=YOUR_NEW_SITE_KEY
# OPENAI_API_KEY_DEV=sk-proj-YOUR_NEW_KEY_HERE  # Or leave empty for dev

# Restart dev containers
docker compose -f deploy/ubuntu/docker-compose.dev.yml restart

# Verify
docker ps --filter 'label=project=munney' --filter 'label=environment=development'
```

---

### Action 4: Update Local Environment

**Priority:** 🔴 **HIGH** | **Time:** 10 minutes

```bash
# On your local machine (WSL2)
cd ~/dev/money

# Create .env.local if it doesn't exist
touch .env.local

# Add to .env.local (or update if exists)
cat >> .env.local << EOF
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
MYSQL_PASSWORD=$(openssl rand -base64 32)
OPENAI_API_KEY=sk-proj-YOUR_NEW_KEY_HERE
RESEND_API_KEY=re_YOUR_NEW_KEY_HERE
HCAPTCHA_SECRET_KEY=ES_YOUR_NEW_SECRET_KEY
HCAPTCHA_SITE_KEY=YOUR_NEW_SITE_KEY
JWT_PASSPHRASE=$(openssl rand -base64 32)
EOF

# Verify .env.local is in .gitignore
grep ".env.local" .gitignore || echo ".env.local" >> .gitignore

# Update backend/.env
nano backend/.env

# Update HCAPTCHA keys:
# HCAPTCHA_SITE_KEY=YOUR_NEW_SITE_KEY
# HCAPTCHA_SECRET_KEY=ES_YOUR_NEW_SECRET_KEY

# Restart local containers
docker compose down
docker compose up -d

# Verify
docker ps
docker logs money-backend --tail 20
```

---

### Action 5: Remove Hardcoded Secrets from Git

**Priority:** 🔴 **CRITICAL** | **Time:** 60 minutes
**⚠️ WARNING:** This will rewrite git history. Coordinate with team!

```bash
cd ~/dev/money

# 1. Install BFG Repo-Cleaner
# On Ubuntu/WSL:
wget https://repo1.maven.org/maven2/com/madgag/bfg/1.14.0/bfg-1.14.0.jar
alias bfg='java -jar bfg-1.14.0.jar'

# On macOS:
brew install bfg

# 2. Create list of secrets to remove
cat > secrets-to-remove.txt << EOF
re_UrrEVv6w_9NEHJayyB1VWJHB9g7bZcgfu
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
ES_e9abae79ed0f4f448f3ef6994d0af93b
+Qdsl7gdFOYMlixhppIKftcHetoUa/G2gxZBBLOx9Is=
moneymakestheworldgoround
27deec7964942d0b60d20fed8bc31d59d4b682fc92dadd89cce60af61e934f24
EOF

# 3. Create backup
cd ..
cp -r money money-backup-$(date +%Y%m%d)

# 4. Run BFG
cd money
bfg --replace-text secrets-to-remove.txt

# 5. Clean up
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# 6. Review changes
git log --all --oneline | head -20

# 7. COORDINATE WITH TEAM before force push!
# 8. Force push (DANGEROUS - everyone will need to re-clone)
# git push --force --all
# git push --force --tags

# 9. Delete secrets file
rm secrets-to-remove.txt
```

**⚠️ COORDINATE WITH TEAM:**
- Notify all developers before force pushing
- Everyone will need to: `git fetch --all && git reset --hard origin/develop`
- Or re-clone the repository

---

## 🟡 HIGH PRIORITY ACTIONS (DO TODAY - NEXT 2 HOURS)

### Action 6: Fix docker-compose.yml to Use Environment Variables

**Priority:** 🟡 **HIGH** | **Time:** 20 minutes

```bash
cd ~/dev/money

# Backup
cp docker-compose.yml docker-compose.yml.backup

# Edit docker-compose.yml
nano docker-compose.yml

# CHANGE THIS:
# environment:
#   DATABASE_URL: "mysql://money:moneymakestheworldgoround@database:3306/money_db?serverVersion=8.0&charset=utf8mb4"
#   MAILER_DSN: "resend+api://re_UrrEVv6w_9NEHJayyB1VWJHB9g7bZcgfu@default"

# TO THIS:
# environment:
#   DATABASE_URL: "mysql://money:${MYSQL_PASSWORD}@database:3306/money_db?serverVersion=8.0&charset=utf8mb4"
#   MAILER_DSN: "resend+api://${RESEND_API_KEY}@default"
#   OPENAI_API_KEY: ${OPENAI_API_KEY}
#   HCAPTCHA_SECRET_KEY: ${HCAPTCHA_SECRET_KEY}

# Also update database section:
# MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
# MYSQL_PASSWORD: ${MYSQL_PASSWORD}

# Save and commit
git add docker-compose.yml
git commit -m "security: Use environment variables instead of hardcoded secrets"
```

---

### Action 7: Fix deploy/ubuntu/docker-compose.prod.yml

**Priority:** 🟡 **HIGH** | **Time:** 10 minutes

```bash
cd ~/dev/money

# Edit production compose file
nano deploy/ubuntu/docker-compose.prod.yml

# LINE 27: REMOVE hardcoded JWT_PASSPHRASE
# CHANGE:
#   JWT_PASSPHRASE: '+Qdsl7gdFOYMlixhppIKftcHetoUa/G2gxZBBLOx9Is='

# TO:
#   JWT_PASSPHRASE: ${JWT_PASSPHRASE_PROD}

# Save and commit
git add deploy/ubuntu/docker-compose.prod.yml
git commit -m "security: Use env var for JWT_PASSPHRASE instead of hardcoding"
```

---

### Action 8: Add Migrations to Dev Deployment Workflow

**Priority:** 🟡 **HIGH** | **Time:** 15 minutes

```bash
cd ~/dev/money

# Edit dev deployment workflow
nano .github/workflows/deploy-dev.yml

# ADD these steps after "Restart containers" step:
# - name: 🗄️ Run database migrations
#   run: |
#     cd /srv/munney-dev
#     docker exec munney-dev-backend php bin/console doctrine:migrations:migrate --no-interaction --env=dev
#     echo "✅ Migrations complete"
#
# - name: 🧹 Clear cache
#   run: |
#     cd /srv/munney-dev
#     docker exec munney-dev-backend php bin/console cache:clear --env=dev
#     docker exec munney-dev-backend php bin/console cache:warmup --env=dev
#     echo "✅ Cache refreshed"

# Save and commit
git add .github/workflows/deploy-dev.yml
git commit -m "fix: Add migrations and cache clear to dev deployment workflow"
```

---

### Action 9: Update .gitignore

**Priority:** 🟡 **HIGH** | **Time:** 5 minutes

```bash
cd ~/dev/money

# Edit .gitignore
nano .gitignore

# Add these lines if not present:
# Environment files
.env
.env.local
.env.*.local
backend/.env
backend/.env.local
backend/.env.*.local
frontend/.env.local
frontend/.env.production

# Backup files
*.backup
*.env.backup*

# JWT keys
backend/config/jwt/*.pem

# Save and commit
git add .gitignore
git commit -m "security: Expand .gitignore to prevent secret leaks"
```

---

### Action 10: Update Frontend with New hCaptcha Site Key

**Priority:** 🟡 **MEDIUM** | **Time:** 10 minutes

```bash
cd ~/dev/money/frontend

# Update .env.production
nano .env.production

# Add/Update:
# VITE_HCAPTCHA_SITE_KEY=YOUR_NEW_SITE_KEY

# Save and commit
git add .env.production
git commit -m "security: Update hCaptcha site key after rotation"
```

---

## 🟢 MEDIUM PRIORITY ACTIONS (DO THIS WEEK)

### Action 11: Clean Up Git Working Directory

**Priority:** 🟢 **MEDIUM** | **Time:** 10 minutes

```bash
cd ~/dev/money

# Review deleted files
git status | grep "deleted:"

# Either commit the deletions:
git add -u
git commit -m "docs: Remove obsolete documentation files"

# OR restore them if deleted by mistake:
# git restore ACCESSIBILITY.md ACCOUNT_SWITCHING_FIX.md ...
```

---

### Action 12: Remove Unused docker-compose.prod.yml

**Priority:** 🟢 **LOW** | **Time:** 2 minutes

```bash
cd ~/dev/money

# The root docker-compose.prod.yml is not used
# The actual prod compose file is deploy/ubuntu/docker-compose.prod.yml

# Rename to .example
mv docker-compose.prod.yml docker-compose.prod.example.yml

# Commit
git add docker-compose.prod.example.yml
git rm docker-compose.prod.yml
git commit -m "refactor: Rename unused docker-compose.prod.yml to .example"
```

---

### Action 13: Clean Up Server Backup Files

**Priority:** 🟢 **LOW** | **Time:** 5 minutes

```bash
# SSH to production
ssh lars@192.168.0.105

# Remove backup files (after verifying .env is correct)
cd /srv/munney-prod
rm .env.backup.20251120_161108
rm .env.backup  # If exists

# Same for dev
cd /srv/munney-dev
rm .env.backup* 2>/dev/null || true
```

---

## ✅ VERIFICATION CHECKLIST

After completing all actions, verify:

### Security
- [ ] All API keys revoked and regenerated
- [ ] Production .env has new keys
- [ ] Dev .env has HCAPTCHA keys
- [ ] Local .env.local has strong passwords
- [ ] docker-compose.yml uses environment variables
- [ ] No hardcoded secrets in any files
- [ ] .gitignore prevents future leaks
- [ ] Git history cleaned (if force pushed)

### Functionality
- [ ] Production site loads: https://munney.munne.me
- [ ] Dev site loads: https://devmunney.home.munne.me
- [ ] Login works on both environments
- [ ] CAPTCHA appears after 3 failed logins
- [ ] CAPTCHA can be solved and login succeeds
- [ ] Email sending works (test account lock email)
- [ ] AI features work (transaction categorization)

### Deployment
- [ ] Dev deployment workflow includes migrations
- [ ] Production JWT keys generated on servers
- [ ] All environment variables documented
- [ ] Rollback procedure documented

### Documentation
- [ ] Team notified of key rotation
- [ ] Deployment guide updated
- [ ] Security incident documented
- [ ] Lessons learned recorded

---

## 📞 ROLLBACK PROCEDURE

If something breaks after these changes:

### Rollback Environment Variables
```bash
# SSH to server
ssh lars@192.168.0.105

# Restore from backup
cd /srv/munney-prod
cp .env.backup.YYYYMMDD_HHMMSS .env

# Restart containers
docker compose -f deploy/ubuntu/docker-compose.prod.yml restart
```

### Rollback Git Changes
```bash
# Local machine
cd ~/dev/money

# Restore from backup
cp -r ../money-backup-YYYYMMDD/* .

# Force reset to before changes
git reset --hard HEAD~10  # Adjust number as needed
```

### Rollback API Keys
- Keep old keys active for 24-48 hours
- Gradual migration instead of immediate cutover
- Monitor for errors using old keys

---

## 🎯 SUCCESS CRITERIA

You're done when:

1. ✅ All API keys are new and secure
2. ✅ No secrets in git history
3. ✅ All environments have correct configuration
4. ✅ CAPTCHA works on all environments
5. ✅ No errors in production logs
6. ✅ Users can log in successfully
7. ✅ Team is notified and aligned
8. ✅ Documentation is updated

---

## 📊 ESTIMATED TIME BREAKDOWN

| Action | Priority | Time | Can Fail? |
|--------|----------|------|-----------|
| 1. Revoke API keys | 🔴 Critical | 30 min | No |
| 2. Update prod env | 🔴 Critical | 15 min | Yes - test first |
| 3. Update dev env | 🔴 High | 10 min | Yes - test first |
| 4. Update local env | 🔴 High | 10 min | No |
| 5. Clean git history | 🔴 Critical | 60 min | Yes - backup first |
| 6-10. Code changes | 🟡 High | 60 min | No |
| 11-13. Cleanup | 🟢 Low | 17 min | No |

**Total Estimated Time:** 3.5 - 4 hours

**Critical Path:** Actions 1-5 (2.5 hours) - Must be done as one block

---

## 🚨 EMERGENCY CONTACTS

If something goes wrong:

1. **Check logs:**
   ```bash
   docker logs munney-prod-backend --tail 100
   docker logs munney-prod-frontend --tail 100
   ```

2. **Restore from backup:**
   ```bash
   cd /srv/munney-prod
   cp .env.backup.YYYYMMDD_HHMMSS .env
   docker compose -f deploy/ubuntu/docker-compose.prod.yml restart
   ```

3. **Verify database:**
   ```bash
   docker exec munney-prod-mysql mysql -u root -p money_db_prod -e "SHOW TABLES;"
   ```

4. **Check container status:**
   ```bash
   docker ps -a --filter 'label=project=munney'
   docker stats --no-stream
   ```

---

**Status:** ⚠️ **AWAITING EXECUTION**
**Next Review:** After Phase 0 completion
**Document:** 04_IMMEDIATE_ACTION_PLAN.md
