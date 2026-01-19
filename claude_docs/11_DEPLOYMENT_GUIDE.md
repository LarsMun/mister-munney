# Deployment Guide

**Last Updated:** January 2026

This guide covers the deployment process for Mister Munney across all environments.

## Environments Overview

| Environment | URL | Branch | Deployment Trigger |
|-------------|-----|--------|-------------------|
| **Local Development** | localhost:3000 | feature/* | Manual |
| **Development/Staging** | devmunney.home.munne.me | develop | Auto on push |
| **Production** | munney.munne.me | main | Auto on push |

## CI/CD Pipeline

### Continuous Integration (CI)

The CI pipeline runs on every push and pull request:

1. **Backend Checks**
   - PHP 8.3 setup
   - Composer dependency installation
   - Security vulnerability scanning (`composer audit`)
   - PHPUnit unit tests
   - Symfony container validation

2. **Frontend Checks**
   - Node.js 20 setup
   - npm dependency installation
   - TypeScript type checking
   - ESLint linting
   - Vitest unit tests
   - Vite production build

### Deployment Pipeline

#### Development (devmunney)

Triggered on push to `develop` branch:

```yaml
# .github/workflows/deploy-dev.yml
on:
  push:
    branches: [develop]
```

Steps:
1. CI checks pass
2. Pull latest code
3. Build Docker images
4. Deploy containers
5. Run migrations
6. Health check

#### Production (munney)

Triggered on push to `main` branch:

```yaml
# .github/workflows/deploy-prod.yml
on:
  push:
    branches: [main]
```

Steps:
1. CI checks pass
2. Create database backup
3. Tag current images for rollback
4. Pull latest code
5. Generate JWT keys (if needed)
6. Build Docker images
7. Deploy containers
8. Run migrations
9. Clear and warm cache
10. Health check
11. Automatic rollback on failure

## Manual Deployment

### Prerequisites

- SSH access to the server
- Docker and Docker Compose installed
- Git repository cloned

### Steps

```bash
# 1. SSH to server
ssh user@server

# 2. Navigate to project
cd /srv/munney-prod  # or /srv/munney-dev

# 3. Pull latest code
git fetch origin
git checkout main  # or develop
git pull

# 4. Build and deploy
docker compose -f deploy/ubuntu/docker-compose.prod.yml down
docker compose -f deploy/ubuntu/docker-compose.prod.yml build --no-cache
docker compose -f deploy/ubuntu/docker-compose.prod.yml up -d

# 5. Run migrations
docker exec munney-prod-backend php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# 6. Clear cache
docker exec munney-prod-backend php bin/console cache:clear --env=prod
docker exec munney-prod-backend php bin/console cache:warmup --env=prod

# 7. Verify
curl -f https://munney.munne.me/api/health
```

## Rollback Procedure

### Automatic Rollback

The CI/CD pipeline automatically triggers rollback if health checks fail:

1. Previous Docker images are tagged as `:rollback` before deployment
2. If deployment fails, `rollback.sh` is executed
3. Rollback restores previous images and restarts containers

### Manual Rollback

```bash
# 1. SSH to server
ssh user@server

# 2. Navigate to project
cd /srv/munney-prod

# 3. Execute rollback script
chmod +x deploy/ubuntu/rollback.sh
./deploy/ubuntu/rollback.sh
```

### Database Rollback

For database issues, restore from backup:

```bash
# List available backups
ls -la /srv/munney-prod/backups/

# Restore specific backup
docker exec -i munney-prod-mysql mysql -u root -p$MYSQL_ROOT_PASSWORD money_db_prod < backups/munney_prod_YYYYMMDD_HHMMSS.sql
```

## Environment Configuration

### Required Environment Variables

Create `.env` file from template:

```bash
cp deploy/ubuntu/.env.prod.example .env
# Edit with your values
nano .env
```

Key variables:
```env
# Application
APP_ENV=prod
APP_SECRET=your-secure-secret

# Database
MYSQL_ROOT_PASSWORD_PROD=secure-password
DATABASE_URL=mysql://money:password@munney-prod-mysql:3306/money_db_prod

# JWT
JWT_PASSPHRASE=your-jwt-passphrase

# Mail
MAIL_FROM_ADDRESS=noreply@example.com
MAILER_DSN=smtp://mailserver:25

# CAPTCHA (optional)
HCAPTCHA_SECRET_KEY=your-secret-key
```

### JWT Key Generation

JWT keys are generated automatically during deployment, or manually:

```bash
cd backend
mkdir -p config/jwt
openssl genpkey -algorithm RSA \
  -out config/jwt/private.pem \
  -aes256 \
  -pass pass:"$JWT_PASSPHRASE" \
  -pkeyopt rsa_keygen_bits:4096
openssl rsa \
  -pubout \
  -in config/jwt/private.pem \
  -out config/jwt/public.pem \
  -passin pass:"$JWT_PASSPHRASE"
```

## Docker Configuration

### Production Compose File

Located at: `deploy/ubuntu/docker-compose.prod.yml`

Services:
- **munney-prod-backend**: PHP-FPM + Symfony API
- **munney-prod-frontend**: Nginx + React SPA
- **munney-prod-mysql**: MySQL 8.0 database
- **munney-prod-traefik**: Reverse proxy with SSL

### Container Management

```bash
# View running containers
docker ps --filter 'label=project=munney'

# View logs
docker compose -f deploy/ubuntu/docker-compose.prod.yml logs -f

# View specific service logs
docker logs munney-prod-backend -f

# Restart specific service
docker compose -f deploy/ubuntu/docker-compose.prod.yml restart backend

# Enter container shell
docker exec -it munney-prod-backend sh
```

## Health Monitoring

### Health Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /api/health` | Full health check (DB, JWT) |
| `GET /api/health/live` | Liveness probe |
| `GET /api/health/ready` | Readiness probe |

### Example Response

```json
{
  "status": "healthy",
  "checks": {
    "database": "ok",
    "jwt_keys": "ok"
  },
  "timestamp": "2026-01-19T20:30:00+00:00"
}
```

### Monitoring Commands

```bash
# Quick health check
curl https://munney.munne.me/api/health

# Check container health
docker inspect --format='{{.State.Health.Status}}' munney-prod-backend

# View container resource usage
docker stats --filter 'label=project=munney'
```

## Database Backups

### Automatic Backups

Configured via cron:

```bash
# Daily backup at 3 AM
0 3 * * * /srv/munney-prod/scripts/backup.sh
```

### Manual Backup

```bash
cd /srv/munney-prod
source .env
BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
docker exec munney-prod-mysql mysqldump -u root -p${MYSQL_ROOT_PASSWORD_PROD} money_db_prod > backups/munney_prod_${BACKUP_DATE}.sql
```

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| Container won't start | Check logs: `docker logs munney-prod-backend` |
| Database connection failed | Verify DATABASE_URL in .env |
| JWT errors | Regenerate keys, check JWT_PASSPHRASE |
| 502 Bad Gateway | Check if backend container is running |
| Migration failed | Check for conflicts, restore backup |

### Debug Mode

For debugging (temporary only):

```bash
# Enable debug in container
docker exec munney-prod-backend sh -c "APP_DEBUG=1 php bin/console debug:container"
```

## Security Considerations

- Never commit `.env` files
- Use strong passwords for database
- Keep JWT passphrase secure
- Regularly rotate secrets
- Monitor for security vulnerabilities

See [12_SECURITY_GUIDE.md](12_SECURITY_GUIDE.md) for detailed security information.
