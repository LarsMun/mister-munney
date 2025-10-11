# 🚀 Productie Deployment Checklist - Dashboard Update

## Pre-Deployment

### ✅ Code Klaar Maken
- [ ] Alle nieuwe code getest op development
- [ ] Dashboard werkt correct op https://devmunney.home.munne.me
- [ ] Geen TypeScript errors
- [ ] Geen console errors in browser
- [ ] Alle features getest:
  - [ ] Hero section toont correcte data
  - [ ] Quick stats grid toont alle 6 metrics
  - [ ] Balans grafiek werkt en is interactief
  - [ ] Statistieken card klapt uit/in
  - [ ] Quick actions werken (import, links)
  - [ ] Insights panel toont relevante meldingen

### ✅ Git Voorbereiden
- [ ] Alle wijzigingen gecommit op `develop` branch
- [ ] Code gepusht naar GitHub
- [ ] Development deployment succesvol
- [ ] Laatste test op dev omgeving gedaan

## Deployment Opties

Je hebt **2 manieren** om naar productie te deployen:

### Optie A: Automatisch via GitHub Actions (Aanbevolen)

**Voordelen:**
- ✅ Volledig geautomatiseerd
- ✅ Backup wordt automatisch gemaakt
- ✅ Health checks ingebouwd
- ✅ Deployment log beschikbaar in GitHub

**Stappen:**
```bash
# 1. Merge develop naar main
git checkout main
git merge develop
git push origin main

# 2. GitHub Actions neemt automatisch over!
# Check: https://github.com/[je-repo]/actions
```

### Optie B: Handmatig via SSH (Meer controle)

**Voordelen:**
- ✅ Meer controle over elk stap
- ✅ Direct feedback
- ✅ Makkelijker troubleshooten

**Stappen:**
```bash
# 1. Merge develop naar main (lokaal)
git checkout main
git merge develop
git push origin main

# 2. SSH naar server
ssh lars@apollowebserv

# 3. Run deployment script
cd /srv/munney-prod
bash deploy/ubuntu/deploy-prod.sh

# Script vraagt bevestiging:
# "Continue? (y/n)"
# Type 'y' en druk Enter
```

## Deployment Proces (Wat gebeurt er?)

```
1. 💾 Database Backup
   └─ munney_prod_YYYYMMDD_HHMMSS.sql in /srv/munney-prod/backups/

2. 📥 Git Pull
   └─ Latest main branch code

3. 🛑 Stop Containers
   └─ Graceful shutdown van backend, frontend, database

4. 🏗️  Build Images
   └─ Fresh build met --no-cache
   └─ Kan 5-10 minuten duren

5. ▶️  Start Containers
   └─ Database eerst
   └─ Dan backend
   └─ Dan frontend

6. ⏳ Wait for Database (15 sec)

7. 🗄️  Run Migrations
   └─ doctrine:migrations:migrate

8. 🧹 Clear Cache
   └─ Symfony cache:clear + warmup

9. 🏥 Health Check
   └─ Test of frontend/backend reageren
```

## Post-Deployment Verificatie

### ✅ Containers Checken
```bash
# SSH naar server
ssh lars@apollowebserv

# Check of alle containers draaien
docker ps | grep munney-prod

# Moet 3 containers tonen:
# - munney-backend-prod
# - munney-frontend-prod  
# - munney-mysql-prod
```

### ✅ Logs Bekijken
```bash
# Frontend logs
docker logs munney-frontend-prod --tail 50

# Backend logs
docker logs munney-backend-prod --tail 50

# Database logs
docker logs munney-mysql-prod --tail 50
```

### ✅ Website Testen

**In Browser:**
1. Ga naar https://munney.home.munne.me
2. Ververs met Ctrl+Shift+R (hard refresh)
3. Check dashboard:
   - [ ] Hero section zichtbaar met balans
   - [ ] Quick stats grid toont 6 kaartjes
   - [ ] Balans grafiek laadt
   - [ ] Statistieken card werkt
   - [ ] Quick actions knoppen werken
   - [ ] Insights panel toont meldingen
4. Test navigatie:
   - [ ] Dashboard → Transacties werkt
   - [ ] Dashboard → Patronen werkt
   - [ ] Dashboard → Budgetten werkt
   - [ ] Terug naar Dashboard werkt

**Via Command Line:**
```bash
# Test frontend
curl -I https://munney.home.munne.me
# Moet "200 OK" geven

# Test backend API
curl -I https://munney.home.munne.me/api
# Moet "200 OK" geven

# Test met browser user agent
curl -A "Mozilla/5.0" https://munney.home.munne.me | grep -i "dashboard"
```

### ✅ Database Checken
```bash
# Verbind met database
ssh lars@apollowebserv
docker exec -it munney-mysql-prod mysql -u money -p money_db_prod

# Check migrations
SELECT * FROM doctrine_migration_versions ORDER BY executed_at DESC LIMIT 5;

# Exit
exit
```

## Rollback Plan (Als er iets misgaat)

### Optie 1: Restart Containers
```bash
ssh lars@apollowebserv
cd /srv/munney-prod
docker compose -f docker-compose.yml -f deploy/ubuntu/docker-compose.prod.yml restart
```

### Optie 2: Terug naar Vorige Commit
```bash
ssh lars@apollowebserv
cd /srv/munney-prod

# Bekijk recente commits
git log --oneline -10

# Ga terug naar vorige commit (vervang COMMIT_HASH)
git checkout COMMIT_HASH

# Redeploy
bash deploy/ubuntu/deploy-prod.sh
```

### Optie 3: Database Restore
```bash
ssh lars@apollowebserv

# Lijst backups
ls -lh /srv/munney-prod/backups/

# Restore (vervang BACKUP_FILE met echte bestandsnaam)
docker exec -i munney-mysql-prod mysql -u root -p${MYSQL_ROOT_PASSWORD_PROD} money_db_prod < /srv/munney-prod/backups/BACKUP_FILE.sql
```

## Troubleshooting

### ❌ Frontend toont oude versie
```bash
# Hard refresh in browser: Ctrl+Shift+R
# Of clear browser cache

# Check of frontend rebuild was:
docker logs munney-frontend-prod | grep -i "build"
```

### ❌ API errors in browser console
```bash
# Check backend logs
docker logs munney-backend-prod --tail 100

# Check CORS settings
docker exec munney-backend-prod cat .env | grep CORS
```

### ❌ Containers blijven crashen
```bash
# Check logs
docker logs munney-backend-prod
docker logs munney-frontend-prod
docker logs munney-mysql-prod

# Check container status
docker ps -a | grep munney-prod

# Volledige rebuild
cd /srv/munney-prod
docker compose -f docker-compose.yml -f deploy/ubuntu/docker-compose.prod.yml down -v
bash deploy/ubuntu/deploy-prod.sh
```

### ❌ Database migrations falen
```bash
# Check migratie status
docker exec munney-backend-prod php bin/console doctrine:migrations:status

# Force migratie als nodig
docker exec munney-backend-prod php bin/console doctrine:migrations:migrate --no-interaction

# Laatste migratie terugdraaien
docker exec munney-backend-prod php bin/console doctrine:migrations:migrate prev
```

## Success Criteria

✅ Deployment is succesvol als:
- [ ] Alle 3 containers draaien (backend, frontend, database)
- [ ] Website bereikbaar op https://munney.home.munne.me
- [ ] Dashboard pagina laadt zonder errors
- [ ] Alle dashboard componenten zichtbaar en functioneel
- [ ] Geen errors in browser console
- [ ] Geen errors in container logs
- [ ] Account selector werkt
- [ ] Transacties pagina nog steeds werkt
- [ ] Database backup aangemaakt

## Communicatie

### Voor Deployment
- [ ] Optioneel: Informeer gebruikers (als er meer zijn dan jij)
- [ ] Kies rustig moment (niet tijdens werkuren als je het zelf gebruikt)

### Na Deployment
- [ ] Test alles grondig
- [ ] Documenteer eventuele issues
- [ ] Backup bevestigen en archiveren

## Post-Deployment Monitoring (Eerste 24 uur)

- [ ] Check logs na 1 uur
- [ ] Check logs na 24 uur
- [ ] Monitor performance in browser DevTools
- [ ] Check of automatische backups nog werken (cron)

## Notes

**Deployment Duur:**
- Totaal: ~10-15 minuten
- Downtime: ~2-3 minuten (tijdens container restart)

**Best Practices:**
- Altijd eerst testen op dev
- Deploy buiten piekuren
- Backup wordt automatisch gemaakt
- Monitor logs na deployment

**Emergency Contact:**
- Git Repo: [link]
- Server: apollowebserv (192.168.0.105)
- Documentation: /srv/munney-prod/deploy/ubuntu/munney_ubuntu_readme.md
