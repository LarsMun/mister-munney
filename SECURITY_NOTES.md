# Security Configuration Notes

## Secrets Management

### ⚠️ IMPORTANT: Never Commit Secrets to Git

All sensitive files are gitignored:
- `.env` (root)
- `backend/.env`
- `config/jwt/*.pem`

### Environment Variables

Production secrets are stored in:
- `/srv/munney-prod/.env` - Main secrets (MySQL passwords, OpenAI key)
- `/srv/munney-prod/backend/.env` - Backend configuration

### Rotating Secrets

If you suspect a secret has been compromised, rotate immediately:

#### 1. OpenAI API Key
```bash
# 1. Get new key from https://platform.openai.com/api-keys
# 2. Update .env files
# 3. Revoke old key on OpenAI dashboard
# 4. Restart backend container
docker restart munney-backend-prod
```

#### 2. Database Passwords
```bash
# 1. Connect to database
docker exec -it munney-mysql-prod mysql -u root -p

# 2. Change passwords
ALTER USER 'money'@'%' IDENTIFIED BY 'new_password_here';
FLUSH PRIVILEGES;

# 3. Update .env and docker-compose.prod.yml
# 4. Restart all containers
cd /srv/munney-prod
docker compose -f docker-compose.prod.yml down
docker compose -f docker-compose.prod.yml up -d
```

#### 3. JWT Keys
```bash
# 1. Generate new keypair
docker exec munney-backend-prod php bin/console lexik:jwt:generate-keypair --overwrite

# 2. This will invalidate all existing tokens (users must re-login)
# 3. Update JWT_PASSPHRASE in .env
# 4. Restart backend
docker restart munney-backend-prod
```

#### 4. Symfony APP_SECRET
```bash
# 1. Generate new secret
openssl rand -hex 32

# 2. Update APP_SECRET in both .env files
# 3. Clear cache and restart
docker exec munney-backend-prod php bin/console cache:clear
docker restart munney-backend-prod
```

## Security Checklist

### After Initial Setup
- [ ] All .env files contain actual secrets (not example values)
- [ ] .env files are NOT committed to git
- [ ] JWT keypair generated
- [ ] Strong database passwords set
- [ ] CORS restricted to production domain
- [ ] HTTPS enabled via Traefik

### Regular Maintenance (Monthly)
- [ ] Check for dependency vulnerabilities: `docker exec munney-backend-prod composer audit`
- [ ] Review failed login attempts (when audit logging implemented)
- [ ] Verify backups are working
- [ ] Check container logs for suspicious activity

### After Suspected Breach
- [ ] Rotate all secrets immediately
- [ ] Review audit logs (when implemented)
- [ ] Check git history for accidentally committed secrets
- [ ] Force all users to re-authenticate (rotate JWT keys)
- [ ] Review database for unauthorized changes

## Current Security Status

### ✅ Implemented
- HTTPS with HSTS headers (via Traefik)
- JWT authentication with Argon2id password hashing
- CORS restricted to production domain only
- Environment-based secret management
- Production containers in read-only mode
- Basic security headers (X-Frame-Options, X-Content-Type-Options, X-XSS-Protection)

### 🔄 In Progress
- Rate limiting
- Content Security Policy headers
- Secure file upload handling
- Audit logging

### 📋 Planned
- Automated security scanning
- Intrusion detection
- Security monitoring/alerting

## Contact

For security issues, contact: [Your contact info]

**Last Updated:** 2025-11-13
