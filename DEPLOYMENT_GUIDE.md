# Deployment Guide

## Environment Overview

- **Local**: `localhost:3000` (Development on WSL2)
- **Dev Server**: `devmunney.home.munne.me` (located at `/srv/munney-dev`)
- **Production**: `munney.munne.me` (located at `/srv/munney-prod`)

## Git Workflow

### Standard Deployment Flow

1. **Develop locally** on the `develop` branch
2. **Push to remote**: `git push origin develop`
3. **Deploy to dev server**:
   ```bash
   ssh webserv "cd /srv/munney-dev && git pull origin develop && docker compose -f deploy/ubuntu/docker-compose.dev.yml up -d --build"
   ```
4. **Test on dev server**: Verify everything works at devmunney.home.munne.me
5. **Merge to main**: Create PR from `develop` → `main` on GitHub
6. **Deploy to production**:
   ```bash
   ssh webserv "cd /srv/munney-prod && git pull origin main && docker compose -f deploy/ubuntu/docker-compose.prod.yml up -d --build"
   ```

### Quick Deploy Commands

**Deploy to Dev:**
```bash
ssh webserv "cd /srv/munney-dev && git pull && docker compose -f deploy/ubuntu/docker-compose.dev.yml up -d --build"
```

**Deploy to Production:**
```bash
ssh webserv "cd /srv/munney-prod && git pull && docker compose -f deploy/ubuntu/docker-compose.prod.yml up -d --build"
```

## Fixing Divergent Branches

If you get "divergent branches" error on a server:

```bash
# On dev server
ssh webserv "cd /srv/munney-dev && git fetch origin && git reset --hard origin/develop && git clean -fd"

# On production server
ssh webserv "cd /srv/munney-prod && git fetch origin && git reset --hard origin/main && git clean -fd"
```

**⚠️ Warning**: This will discard all local changes on the server. Make sure there's nothing important there first with `git status`.

## Files Ignored (Won't Cause Conflicts)

The following are automatically ignored and won't cause git conflicts:

- `backend/public/uploads/` - User uploaded files
- `backend/config/packages/*.local` - Environment-specific config
- `.env.backup`, `*.backup` - Backup files
- `synology/` - Server-specific scripts

## Running Migrations

After deploying backend changes that include migrations:

**Dev:**
```bash
ssh webserv "docker exec munney-dev-backend php bin/console doctrine:migrations:migrate --no-interaction"
```

**Production:**
```bash
ssh webserv "docker exec munney-prod-backend php bin/console doctrine:migrations:migrate --no-interaction"
```

## Troubleshooting

### "Need to specify how to reconcile divergent branches"

This happens when local commits differ from remote. Solution:
```bash
git fetch origin
git reset --hard origin/develop  # or origin/main for prod
git clean -fd
```

### Permission Issues in Production

If you see permission errors in logs:
```bash
ssh webserv "docker exec munney-prod-backend chown -R www-data:www-data /var/www/html/var"
ssh webserv "docker exec munney-prod-backend chmod -R 775 /var/www/html/var"
```

### Check Container Status

```bash
# Dev
ssh webserv "docker ps | grep munney-dev"

# Production
ssh webserv "docker ps | grep munney-prod"
```

### View Container Logs

```bash
# Dev backend logs
ssh webserv "docker logs munney-dev-backend --tail 50"

# Production backend logs
ssh webserv "docker logs munney-prod-backend --tail 50"
```

## Security Checklist Before Production Deploy

- [ ] All secrets in `.env.prod` (not committed to git)
- [ ] CORS configured for production domain only
- [ ] Rate limiting enabled (300 req/min)
- [ ] HTTPS enforced (check Traefik labels)
- [ ] JWT keys generated and secured
- [ ] Database backups configured
- [ ] Test authentication on production

## Notes

- **Never commit** `.env.local`, `.env.prod`, or any file with real secrets
- **Always test on dev** before deploying to production
- **Run migrations** after pulling database schema changes
- **Rebuild containers** when Dockerfile or dependencies change (use `--build` flag)
