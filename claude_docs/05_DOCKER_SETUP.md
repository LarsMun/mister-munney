# Docker Setup Documentation

## Environment Overview

The application runs in three distinct environments:

| Environment | Purpose | Compose File | Location |
|-------------|---------|--------------|----------|
| Local | Development on workstation | `docker-compose.yml` | Root |
| Dev Server | Integration testing | `deploy/ubuntu/docker-compose.dev.yml` | /srv/munney-dev |
| Prod Server | Production | `deploy/ubuntu/docker-compose.prod.yml` | /srv/munney-prod |

## Local Development

### Starting the Environment
```bash
cd /home/lars/dev/money
docker compose up -d
```

### Services

| Service | Container | Port | Description |
|---------|-----------|------|-------------|
| backend | money-backend | 8787:80 | Symfony API |
| frontend | money-frontend | 3000:5173 | Vite dev server |
| database | money-mysql | 3333:3306 | MySQL 8.0 |

### Key Features
- **Hot reload**: Both frontend and backend have volume mounts for live changes
- **Composer cache**: Shared composer cache at `~/.composer`
- **WSL support**: CHOKIDAR_USEPOLLING for file watching

### Volume Mounts
```yaml
backend:
  volumes:
    - ./backend:/var/www/html
    - ./backend/bin/php.ini:/usr/local/etc/php/conf.d/custom.ini
    - ~/.composer:/home/symfony/.composer:cached

frontend:
  volumes:
    - ./frontend:/app
    - /app/node_modules  # Prevents host node_modules from mounting
```

## Development Server

### Location
```
/srv/munney-dev/
```

### URL
```
https://devmunney.home.munne.me
```

### Services

| Service | Container | Network |
|---------|-----------|---------|
| munney-dev-backend | munney-dev-backend | proxy, munney-dev-network |
| munney-dev-frontend | munney-dev-frontend | proxy |
| munney-dev-mysql | munney-dev-mysql | munney-dev-network |

### Traefik Integration
```yaml
labels:
  - "traefik.enable=true"
  - "traefik.docker.network=proxy"
  - "traefik.http.routers.munney-dev-backend.rule=Host(`devmunney.home.munne.me`) && PathPrefix(`/api`)"
  - "traefik.http.routers.munney-dev-backend.entrypoints=websecure"
  - "traefik.http.routers.munney-dev-backend.tls.certresolver=le"
```

### Key Differences from Local
- Traefik as reverse proxy (no direct port exposure)
- HTTPS with Let's Encrypt certificates
- Different database name: `money_db_dev`
- Environment-specific variables (`MYSQL_PASSWORD_DEV`)

## Production Server

### Location
```
/srv/munney-prod/
```

### URL
```
https://munney.munne.me
```

### Services

| Service | Container | Notes |
|---------|-----------|-------|
| munney-prod-backend | munney-prod-backend | Optimized build |
| munney-prod-frontend | munney-prod-frontend | Nginx static |
| munney-prod-mysql | munney-prod-mysql | Persistent data |

### Security Features
- **HSTS headers**: Strict Transport Security enabled
- **Read-only mounts**: JWT keys mounted read-only
- **No source volumes**: Production uses built images only
- **HTTP redirect**: All HTTP traffic redirected to HTTPS

### Traefik Labels (Production)
```yaml
labels:
  # HTTPS
  - "traefik.http.routers.munney-prod-backend.rule=Host(`munney.munne.me`) && PathPrefix(`/api`)"
  - "traefik.http.routers.munney-prod-backend.middlewares=munney-security"

  # Security middleware
  - "traefik.http.middlewares.munney-security.headers.stsSeconds=31536000"
  - "traefik.http.middlewares.munney-security.headers.stsIncludeSubdomains=true"
  - "traefik.http.middlewares.munney-security.headers.stsPreload=true"

  # HTTP to HTTPS redirect
  - "traefik.http.middlewares.munney-redirect-to-https.redirectscheme.scheme=https"
```

## Dockerfiles

### Backend Development (`backend/Dockerfile`)
- Base: `php:8.3-apache`
- Includes: git, zip, composer
- Runs composer install during build
- Uses `symfony` user for composer operations
- Apache configured for Symfony routing

### Backend Production (`backend/Dockerfile.prod`)
- Same base as development
- Copies `.env.prod` as `.env`
- Includes `php-uploads.ini` for file upload settings
- Creates uploads directory with proper permissions

### Frontend Development (`frontend/Dockerfile`)
- Base: `node:20`
- Runs `npm install` during build
- Uses entrypoint script for permission fixes
- Default command: `npm run dev`

### Frontend Production (`frontend/Dockerfile.prod`)
- **Multi-stage build**
- Stage 1: `node:20-alpine` - builds the app
- Stage 2: `nginx:alpine` - serves static files
- VITE_API_URL passed as build arg
- Optimized final image ~30MB

## Networks

### Local
- Default Docker bridge network

### Server (Dev & Prod)
```yaml
networks:
  proxy:
    external: true  # Shared Traefik network
  munney-dev-network:  # or munney-prod-network
    driver: bridge
```

## Volumes

### Local
```yaml
volumes:
  db_data:  # MySQL data persistence
```

### Development Server
```yaml
volumes:
  munney-dev-mysql-data:
    name: munney_dev_mysql_data
```

### Production Server
```yaml
volumes:
  munney-prod-db-data:
    name: munney_db_data_prod
```

## Environment Variables

### Required Variables (All Environments)

| Variable | Description |
|----------|-------------|
| MYSQL_ROOT_PASSWORD | MySQL root password |
| MYSQL_PASSWORD | Application user password |
| OPENAI_API_KEY | OpenAI API key for AI features |
| JWT_PASSPHRASE | JWT signing passphrase |
| HCAPTCHA_SECRET_KEY | hCaptcha validation key |
| HCAPTCHA_SITE_KEY | hCaptcha frontend key |

### Production-Only Variables

| Variable | Description |
|----------|-------------|
| APP_SECRET_PROD | Symfony app secret |
| MAILER_DSN | Email provider DSN |
| MAIL_FROM_ADDRESS | Sender email |
| MAIL_FROM_NAME | Sender name |
| APP_URL | Application base URL |

## Common Commands

### Local Development
```bash
# Start all services
docker compose up -d

# View logs
docker compose logs -f backend

# Enter backend container
docker exec -it money-backend bash

# Run migrations
docker exec money-backend php bin/console doctrine:migrations:migrate

# Clear cache
docker exec money-backend php bin/console cache:clear
```

### Server Management
```bash
# Development
cd /srv/munney-dev
docker compose -f deploy/ubuntu/docker-compose.dev.yml logs -f

# Production
cd /srv/munney-prod
docker compose -f deploy/ubuntu/docker-compose.prod.yml ps
```

## Health Checks

MySQL containers include health checks:
```yaml
healthcheck:
  test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p${PASSWORD}"]
  interval: 10s
  retries: 5
  start_period: 30s
```

Backend waits for healthy database:
```yaml
depends_on:
  database:
    condition: service_healthy
```
