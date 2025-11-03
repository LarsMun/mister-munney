# Ontbrekende Categorieën in Munney

Deze categorieën uit de oude database zijn **niet** gevonden in Munney.

## ⚡ Aanbevolen: Automatisch aanmaken

Je hoeft deze categorieën **NIET** meer handmatig aan te maken! Gebruik in plaats daarvan:

```bash
docker exec money-backend php bin/console app:migrate-categories-from-old-db --create-categories --account-id=1
```

Dit script maakt automatisch alle 43 ontbrekende categorieën aan, inclusief hun kleuren uit de oude database.

## 📋 Handmatige optie

Als je liever handmatig wilt selecteren welke categorieën je wilt aanmaken, kun je ze één voor één aanmaken in Munney met exact dezelfde naam als hieronder.

## Lijst van ontbrekende categorieën

### Algemene uitgaven
- **Abonnementen** (oude ID: 143)
- **Cadeautjes** (oude ID: 156)
- **Diversen** (oude ID: 155)
- **Kleding etc** (oude ID: 171)
- **Onderhoud huis** (oude ID: 139)

### Eten & Drinken
- **Drank** (oude ID: 166)
- **Uiteten** (oude ID: 138)
- **Overblijven** (oude ID: 176)

### Uitgaan & Vakantie
- **Uitgaan** (oude ID: 135)
- **Uitjes** (oude ID: 137)
- **Vakantie** (oude ID: 170)

### Vaste lasten
- **Energie & water** (oude ID: 141)
- **Gemeente & waterschap** (oude ID: 153)
- **Internet & tv** (oude ID: 142)
- **Verzekeringen** (oude ID: 140)
- **Kosten** (oude ID: 154)

### Auto
- **Auto onderhoud** (oude ID: 172)
- **Tanken** (oude ID: 157)

### Kinderen
- **Kinderopvang** (oude ID: 151)
- **Kinderopvangtoeslag** (oude ID: 162)
- **Kinderbijslag** (oude ID: 163)
- **Sparen voor Nora** (oude ID: 147)

### Huis & Inrichting
- **Inrichting & tuin** (oude ID: 164)
- **Badkamer** (oude ID: 174)
- **Gang & dak** (oude ID: 175)
- **Lekkage Kamer Puk** (oude ID: 177)

### Apparaten & Elektronica
- **Apparaten** (oude ID: 173)
- **Elektronica** (oude ID: 178)

### Inkomsten
- **Inkomend** (oude ID: 159)
- **Maandelijkse inleg** (oude ID: 160)
- **Kinderbijslag** (oude ID: 163)
- **Kinderopvangtoeslag** (oude ID: 162)
- **Voorlopige teruggave** (oude ID: 161)
- **Bijstorting** (oude ID: 168)
- **Correctie** (oude ID: 167)

### Spaarrekeningen (met rekeningnummers)
Deze categorieën lijken gekoppeld te zijn aan specifieke spaarrekeningen:
- **Apparaten W55289539** (oude ID: 145)
- **Auto F54892305** (oude ID: 144)
- **Inrichting V54892360** (oude ID: 146)
- **Onderhoud V39525890** (oude ID: 149)
- **Vakantie K54892308** (oude ID: 148)

## Statistieken

- **Totaal aantal ontbrekende categorieën:** 39
- **Aantal transacties die deze categorieën nodig hebben:** 2,580
- **Aantal transacties die nu al gemigreerd kunnen worden:** 976

## Aanbeveling

1. **Prioriteit hoog**: Maak de algemene categorieën aan (Abonnementen, Verzekeringen, Kinderopvang, etc.)
2. **Prioriteit middel**: Maak de inkomsten categorieën aan als je inkomsten wilt tracken
3. **Prioriteit laag**: De spaarrekening-specifieke categorieën lijken gekoppeld aan oude spaarrekeningen die mogelijk niet meer actief zijn

## Gebruik van het migratiescript

### Automatisch (aanbevolen)

Voer het migratiescript uit met de `--create-categories` optie:

```bash
# Eerst dry-run om te zien wat er zou gebeuren
docker exec money-backend php bin/console app:migrate-categories-from-old-db --dry-run --create-categories --account-id=1

# Als je tevreden bent, voer de migratie uit
docker exec money-backend php bin/console app:migrate-categories-from-old-db --create-categories --account-id=1
```

### Handmatig

Als je categorieën handmatig hebt aangemaakt:
1. Voer het migratiescript opnieuw uit met dry-run:
   ```bash
   docker exec money-backend php bin/console app:migrate-categories-from-old-db --dry-run
   ```
2. Als je tevreden bent, voer dan de daadwerkelijke migratie uit:
   ```bash
   docker exec money-backend php bin/console app:migrate-categories-from-old-db
   ```
