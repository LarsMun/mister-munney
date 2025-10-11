# 🚀 Quick Productie Deployment - TL;DR

## Snelle Deployment (Meest Gebruikte Methode)

### 1️⃣ Code Klaar Maken
```bash
# Zorg dat alles op develop staat en getest is
git checkout develop
git add .
git commit -m "feat: Add dashboard with hero section, stats, and insights"
git push origin develop

# Test op dev: https://devmunney.home.munne.me
```

### 2️⃣ Merge naar Main
```bash
git checkout main
git merge develop
git push origin main
```

### 3️⃣ Deploy via SSH
```bash
# SSH naar server
ssh lars@apollowebserv

# Deploy met script
cd /srv/munney-prod
bash deploy/ubuntu/deploy-prod.sh

# Bevestig met 'y' wanneer gevraagd
```

### 4️⃣ Test
- Open https://munney.home.munne.me
- Hard refresh: Ctrl+Shift+R
- Check of dashboard werkt

## Of: Automatisch via GitHub Actions

```bash
# Merge naar main (stap 1 & 2 hierboven)
git checkout main
git merge develop  
git push origin main

# GitHub Actions deployed automatisch!
# Check: https://github.com/[repo]/actions
```

## Belangrijke Commands

### Container Status
```bash
ssh lars@apollowebserv
docker ps | grep munney-prod
```

### Logs Bekijken
```bash
# Frontend
docker logs munney-frontend-prod -f

# Backend  
docker logs munney-backend-prod -f

# Database
docker logs munney-mysql-prod -f
```

### Restart Als Nodig
```bash
cd /srv/munney-prod
docker compose -f docker-compose.yml -f deploy/ubuntu/docker-compose.prod.yml restart
```

## Backup Locatie
```
/srv/munney-prod/backups/munney_prod_YYYYMMDD_HHMMSS.sql
```

## Troubleshooting

**Website toont oude versie?**
→ Hard refresh browser (Ctrl+Shift+R)

**Container crashed?**
→ `docker logs [container-name]`

**API errors?**
→ `docker logs munney-backend-prod --tail 100`

**Complete reset?**
```bash
cd /srv/munney-prod
docker compose -f docker-compose.yml -f deploy/ubuntu/docker-compose.prod.yml down
bash deploy/ubuntu/deploy-prod.sh
```

## URLs

- **Productie:** https://munney.home.munne.me
- **Development:** https://devmunney.home.munne.me
- **Traefik Dashboard:** https://traefik.home.munne.me

## Wat Doet het Deploy Script?

1. 💾 Backup database
2. 📥 Pull latest code
3. 🏗️  Build images (--no-cache)
4. 🔄 Stop & start containers
5. 🗄️  Run migrations
6. 🧹 Clear cache
7. ✅ Health check

**Duur:** ~10-15 minuten  
**Downtime:** ~2-3 minuten

## Success Check

✅ Deployment geslaagd als:
- Dashboard laadt op https://munney.home.munne.me
- Geen errors in console (F12)
- Alle componenten zichtbaar
- Quick actions werken

---

**Need More Info?**  
Zie: `PRODUCTION_DEPLOYMENT_CHECKLIST.md` voor volledige details
