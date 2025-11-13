# Security Fix: OpenAI API Key Exposure

**Date:** 2025-11-13
**Severity:** HIGH
**Status:** Fixed (Key secured, rotation recommended)

## Issue

The OpenAI API key was stored in `backend/.env`, which while gitignored, is not the proper location for secrets. The key has been moved to `backend/.env.local` for better security.

## Actions Taken

✅ **Immediate Actions (Completed):**
1. Removed API key from `backend/.env`
2. Created `backend/.env.local` with the actual API key
3. Created root `.env.local` for Docker Compose variables
4. Updated `docker-compose.yml` to let Symfony load API key from `.env.local`
5. Verified `.env.local` files are properly gitignored
6. Confirmed the key was never committed to git history
7. Tested that backend can still access the API key

## Recommended Follow-Up Actions

### 1. Rotate the OpenAI API Key (Recommended)

Although the key was never committed to git, it's good practice to rotate it:

1. Go to https://platform.openai.com/api-keys
2. Revoke the old key: `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`
3. Generate a new API key
4. Update both:
   - `backend/.env.local` (for local development)
   - Your production server's environment variables

### 2. Production/Dev Server Configuration

For your remote servers, ensure API keys are stored securely:

**Dev Server (`devmunney.home.munne.me`):**
- API key should be in `/srv/munney-dev/.env` (not committed)
- Or passed via Docker Compose environment variable
- Never commit to git

**Production Server (`munney.munne.me`):**
- API key should be in `/srv/munney-prod/.env` (not committed)
- Or passed via Docker Compose environment variable
- Use Symfony Secrets Vault for production (see below)

### 3. Consider Symfony Secrets Vault (Production Best Practice)

For production, consider using Symfony's encrypted secrets:

```bash
# On production server
cd /srv/munney-prod/backend
php bin/console secrets:set OPENAI_API_KEY
# Enter your API key when prompted
# Commit the encrypted secrets to git
```

This encrypts secrets and allows them to be committed to version control safely.

## File Structure (Current Setup)

```
money/
├── .env.local (gitignored)          # Docker Compose variables
├── .gitignore                        # Ensures .env.local is ignored
├── backend/
│   ├── .env                          # Template with placeholders
│   ├── .env.local (gitignored)       # Your actual secrets
│   ├── .env.prod.example             # Production template
│   └── .gitignore                    # Ensures .env.local is ignored
└── docker-compose.yml                # Loads from backend/.env.local
```

## Verification

Test that the API key is working:

```bash
# Check backend can access the key
docker exec money-backend php bin/console cache:clear

# Test AI categorization endpoint (if implemented)
curl -X POST http://localhost:8787/api/transactions/categorize \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"description": "Test transaction"}'
```

## Prevention

**For Future Development:**

1. ✅ Never put secrets in `.env` (use `.env.local` instead)
2. ✅ Always verify `.env.local` is in `.gitignore`
3. ✅ Use `.env.example` files to document required variables
4. ✅ For production, use Symfony Secrets Vault or secure environment variables
5. ✅ Regularly rotate API keys (every 90 days recommended)
6. ⚠️ Set up secret scanning in CI/CD (e.g., GitHub Secret Scanning)

## Related Security Improvements

Next steps in hardening:
- Add security headers (CSP, HSTS, X-Frame-Options, etc.)
- Update vulnerable dependencies (CVE-2025-64500 in symfony/http-foundation)
- Hide server version headers (X-Powered-By)
- Implement request/response logging

## References

- [Symfony Environment Variables](https://symfony.com/doc/current/configuration.html#configuration-based-on-environment-variables)
- [Symfony Secrets Management](https://symfony.com/doc/current/configuration/secrets.html)
- [OpenAI API Key Best Practices](https://platform.openai.com/docs/guides/production-best-practices)
