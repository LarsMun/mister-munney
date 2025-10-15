# 🎯 Munney Container Overview - Clean Setup

## 📦 Container Naming Convention

All containers follow: `munney-{environment}-{service}`

### Local (WSL/Docker Desktop)
- `munney-local-backend` - PHP/Symfony backend
- `munney-local-frontend` - React/Vite frontend  
- `munney-local-mysql` - MySQL database
- **Port**: 3330 (MySQL), 8686 (backend), 5173 (frontend)
- **Access**: http://localhost:5173

### Development (Server: /srv/munney-dev)
- `munney-dev-backend` - PHP/Symfony backend
- `munney-dev-frontend` - React/Vite frontend
- `munney-dev-mysql` - MySQL database
- **Port**: 3334 (MySQL only, others via Traefik)
- **Access**: https://devmunney.home.munne.me

### Production (Server: /srv/munney-prod)
- `munney-prod-backend` - PHP/Symfony backend
- `munney-prod-frontend` - React/Vite frontend
- `munney-prod-mysql` - MySQL database
- **Port**: 3333 (MySQL only, others via Traefik)
- **Access**: https://munney.home.munne.me

## 🚀 Quick Commands

### Filter Munney Containers
```bash
# All Munney containers
docker ps --filter 'label=project=munney'

# Only production
docker ps --filter 'label=environment=production'

# Only development
docker ps --filter 'label=environment=development'

# Only local
docker ps --filter 'label=environment=local'
```

### Local Development (WSL)
```bash
# Start
docker compose up -d

# Stop
docker compose down

# Rebuild
docker compose up -d --build

# Logs
docker logs munney-local-backend -f
docker logs munney-local-frontend -f
docker logs munney-local-mysql -f
```

### Server Development
```bash
cd /srv/munney-dev
bash deploy/ubuntu/deploy-dev.sh

# Or manual:
docker compose -f deploy/ubuntu/docker-compose.dev.yml up -d --build
```

### Server Production
```bash
cd /srv/munney-prod
bash deploy/ubuntu/deploy-prod.sh

# Or manual:
docker compose -f deploy/ubuntu/docker-compose.prod.yml up -d --build
```

## 🗂️ File Structure

```
money/
├── docker-compose.yml                    # Local WSL development
├── deploy/ubuntu/
│   ├── docker-compose.dev.yml           # Server development (standalone)
│   ├── docker-compose.prod.yml          # Server production (standalone)
│   ├── deploy-dev.sh                    # Dev deployment script
│   └── deploy-prod.sh                   # Prod deployment script
├── .github/workflows/
│   ├── deploy-dev.yml                   # Auto-deploy develop → server dev
│   └── deploy-prod.yml                  # Auto-deploy main → server prod
```

## 🔌 Port Mapping

| Environment | Backend | Frontend | MySQL |
|-------------|---------|----------|-------|
| Local       | 8686    | 5173     | 3330  |
| Dev Server  | Traefik | Traefik  | 3334  |
| Prod Server | Traefik | Traefik  | 3333  |

**Note**: Server environments don't expose backend/frontend ports directly - 
everything goes through Traefik reverse proxy with SSL.

## 🎨 Benefits of This Setup

### ✅ Clarity
- Consistent naming across all environments
- Easy to see which container belongs where
- Labels make filtering simple

### ✅ No Conflicts
- Each environment uses different ports
- No base docker-compose causing confusion
- Standalone files = clear dependencies

### ✅ Easy Management
```bash
# Stop ALL Munney containers at once
docker stop $(docker ps -q --filter 'label=project=munney')

# Remove ALL stopped Munney containers
docker rm $(docker ps -aq --filter 'label=project=munney')

# See only what you need
docker ps --filter 'label=project=munney'
```

### ✅ Professional
- Follows Docker best practices
- Microservices architecture
- Easy to scale individual services
- Industry-standard approach

## 🔧 Troubleshooting

### Container won't start
```bash
# Check logs
docker logs munney-{env}-{service}

# Inspect container
docker inspect munney-{env}-{service}
```

### Port already in use
```bash
# Find what's using the port
sudo lsof -i :3333

# Or kill all Munney containers
docker stop $(docker ps -q --filter 'label=project=munney')
```

### Database connection issues
```bash
# Test database connection
docker exec munney-prod-backend php bin/console dbal:run-sql "SELECT 1"

# Check database status
docker exec -it munney-prod-mysql mysql -u money -p money_db_prod
```

### Clean slate
```bash
# Stop and remove everything
docker stop $(docker ps -q --filter 'label=project=munney')
docker rm $(docker ps -aq --filter 'label=project=munney')
docker volume prune -f

# Redeploy
cd /srv/munney-prod
bash deploy/ubuntu/deploy-prod.sh
```

## 📊 Example Output

When you run `docker ps --filter 'label=project=munney'`:
```
CONTAINER ID   IMAGE                    NAMES                    STATUS
abc123         munney-prod-frontend     munney-prod-frontend     Up 2 hours
def456         munney-prod-backend      munney-prod-backend      Up 2 hours
ghi789         mysql:8.0                munney-prod-mysql        Up 2 hours (healthy)
jkl012         munney-dev-frontend      munney-dev-frontend      Up 5 hours
mno345         munney-dev-backend       munney-dev-backend       Up 5 hours
pqr678         mysql:8.0                munney-dev-mysql         Up 5 hours (healthy)
```

Clean, organized, professional! 🎉
