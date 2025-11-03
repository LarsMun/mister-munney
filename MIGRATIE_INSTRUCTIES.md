# Migratie instructies: Categorieën van oude geld-app naar Munney

Dit document beschrijft hoe je categorietoekenningen van je oude geld-app kunt migreren naar Munney.

## Overzicht

Het migratiescript (`MigrateCategoriesFromOldDbCommand`) analyseert de oude database dump en matcht transacties op basis van:
- **Datum** (exacte match)
- **Bedrag** (met kleine tolerantie voor rounding errors)
- **Beschrijving** (exacte match, case-insensitive match, of substring match)

Het script wijst alleen categorieën toe aan transacties die **nog geen categorie hebben**. Bestaande categorietoekenningen worden NIET overschreven.

### Nieuwe feature: Automatisch categorieën aanmaken

Het script kan nu automatisch ontbrekende categorieën aanmaken met de `--create-categories` optie. Dit maakt de migratie veel eenvoudiger:
- ✅ Automatisch alle ontbrekende categorieën aanmaken
- ✅ Kleuren uit de oude database worden overgenomen
- ✅ Alle transacties in één keer categoriseren
- ✅ Dry-run modus om te zien wat er zou gebeuren

## Vereisten

1. SQL dump van de oude database: `old_db/geld-2025-10-31.sql`
2. (Optioneel) Account ID als je `--create-categories` wilt gebruiken (het script kan dit voor je opzoeken)

**Let op**: Met de nieuwe `--create-categories` optie hoef je categorieën NIET meer handmatig aan te maken!

## Gebruik

### Methode 1: Automatisch categorieën aanmaken (aanbevolen)

Dit is de snelste en makkelijkste methode. Het script maakt automatisch alle ontbrekende categorieën aan, inclusief hun kleuren uit de oude database.

#### Stap 1: Bepaal je account ID

Bekijk welke accounts beschikbaar zijn:

```bash
docker exec money-backend php bin/console doctrine:query:sql "SELECT id, name FROM account"
```

Bijvoorbeeld:
```
 ---- -------------
  id   name
 ---- -------------
  1    LM Munne
  2    Koens Munne
 ---- -------------
```

#### Stap 2: Dry-run met automatische categorie aanmaak

Voer eerst een dry-run uit om te zien wat er zou gebeuren:

```bash
docker exec money-backend php bin/console app:migrate-categories-from-old-db --dry-run --create-categories --account-id=1
```

Dit toont:
- Welke categorieën aangemaakt zouden worden (met kleuren)
- Hoeveel transacties gecategoriseerd zouden worden
- Gedetailleerde statistieken

#### Stap 3: Voer de migratie uit

Als je tevreden bent met de dry-run, voer dan de daadwerkelijke migratie uit:

```bash
docker exec money-backend php bin/console app:migrate-categories-from-old-db --create-categories --account-id=1
```

**Let op**: Vervang `--account-id=1` met het juiste account ID voor jouw situatie.

---

### Methode 2: Handmatig categorieën aanmaken

Als je liever zelf controle hebt over welke categorieën worden aangemaakt, kun je deze methode gebruiken.

#### Stap 1: Dry-run (zonder --create-categories)

Voer eerst een dry-run uit om te zien welke categorieën ontbreken:

```bash
docker exec money-backend php bin/console app:migrate-categories-from-old-db --dry-run
```

Dit geeft een overzicht van:
- Hoeveel transacties gematcht kunnen worden
- Hoeveel categorieën toegewezen zouden worden
- Welke categorieën uit de oude database niet gevonden worden in Munney

#### Stap 2: Ontbrekende categorieën aanmaken

Maak de gewenste categorieën handmatig aan via de Munney UI met exact dezelfde naam als in de oude database.

Bijvoorbeeld:
- Oude categorie: "Boodschappen" → Maak aan in Munney als "Boodschappen"
- Oude categorie: "Vaste lasten" → Maak aan in Munney als "Vaste lasten"

#### Stap 3: Migratie uitvoeren

Voer de migratie uit:

```bash
docker exec money-backend php bin/console app:migrate-categories-from-old-db
```

Het script zal:
- Alle transacties in Munney doorlopen
- Transacties zonder categorie proberen te matchen met de oude database
- De categorie toewijzen als er een match gevonden wordt

### Optionele parameters

- `--sql-file`: Specificeer een ander SQL bestand (standaard: `old_db/geld-2025-10-31.sql`)
  ```bash
  docker exec money-backend php bin/console app:migrate-categories-from-old-db --sql-file=old_db/ander-bestand.sql
  ```

- `--dry-run`: Voer een dry-run uit zonder wijzigingen door te voeren
  ```bash
  docker exec money-backend php bin/console app:migrate-categories-from-old-db --dry-run
  ```

- `--create-categories`: Maak automatisch ontbrekende categorieën aan (vereist `--account-id`)
  ```bash
  docker exec money-backend php bin/console app:migrate-categories-from-old-db --create-categories --account-id=1
  ```

- `--account-id`: Account ID waar nieuwe categorieën aan gekoppeld moeten worden (verplicht bij `--create-categories`)
  ```bash
  docker exec money-backend php bin/console app:migrate-categories-from-old-db --create-categories --account-id=1
  ```

## Output

Het script toont een gedetailleerd rapport met de volgende statistieken:

| Statistiek | Beschrijving |
|-----------|--------------|
| **Totaal aantal transacties in Munney** | Totaal aantal transacties in de Munney database |
| **Transacties zonder categorie** | Aantal transacties die nog geen categorie hebben |
| **Gematcht met oude database** | Aantal transacties die succesvol gematcht zijn met de oude database |
| **Categorieën toegewezen** | Aantal transacties waaraan een categorie is toegewezen |
| **Niet gevonden in oude database** | Transacties in Munney die niet gevonden zijn in de oude database |
| **Geen categorie in oude database** | Transacties die wel gematcht zijn maar geen categorie hadden in de oude database |
| **Categorie niet gevonden in Munney** | Aantal categorieën uit de oude database die niet gevonden zijn in Munney (alleen bij handmatige methode) |
| **Nieuwe categorieën aangemaakt** | Aantal nieuwe categorieën dat is aangemaakt (alleen bij `--create-categories`) |

## Matching strategie

Het script gebruikt de volgende matching strategie (in volgorde):

1. **Datum + Bedrag**: Eerst worden transacties gefilterd op exacte datum en bedrag (±0.01 tolerantie)
2. **Exacte omschrijving**: Exacte match van de beschrijving
3. **Combined match**: Match van beschrijving + notities (case-insensitive)
4. **Substring match**: Substring match van de beschrijving (minimaal 10 karakters)

### Categorienaam matching

Categorieën worden gematcht op basis van naam:

1. **Exacte match**: Exacte match van de categorienaam binnen het account
2. **Case-insensitive match**: Match zonder hoofdlettergevoeligheid

**Let op**: Categorieën zijn gekoppeld aan accounts in Munney. Het script zoekt alleen naar categorieën binnen het account van de transactie.

## Veiligheid

- Het script overschrijft **NOOIT** bestaande categorietoekenningen
- Bij dry-run worden geen wijzigingen doorgevoerd (inclusief geen categorieën aanmaken)
- Het script gebruikt Doctrine transactions (bij een fout worden alle wijzigingen teruggedraaid)
- Nieuwe categorieën worden alleen aangemaakt als `--create-categories` is opgegeven
- Categorieën worden intelligent gecached om duplicaten te voorkomen

## Troubleshooting

### Probleem: "Categorie niet gevonden in Munney"

**Oplossing 1 (aanbevolen)**: Gebruik de `--create-categories` optie om categorieën automatisch aan te laten maken.

**Oplossing 2**: Maak de ontbrekende categorie handmatig aan in Munney met exact dezelfde naam als in de oude database.

### Probleem: "De --account-id optie is verplicht bij gebruik van --create-categories"

**Oplossing**: Bepaal eerst je account ID met:
```bash
docker exec money-backend php bin/console doctrine:query:sql "SELECT id, name FROM account"
```
En gebruik dan het juiste account ID:
```bash
docker exec money-backend php bin/console app:migrate-categories-from-old-db --create-categories --account-id=1
```

### Probleem: Weinig matches

**Oplossing**:
- Controleer of de datums in beide databases overeenkomen
- Controleer of de bedragen hetzelfde zijn (let op: Munney slaat bedragen op in cents)
- Check of de beschrijvingen vergelijkbaar zijn

### Probleem: "SQL bestand niet gevonden"

**Oplossing**: Controleer of het pad naar het SQL bestand correct is. Het standaard pad is `old_db/geld-2025-10-31.sql` relatief ten opzichte van de backend directory.

## Technische details

### Database structuur (oude database)

- **`mutaties`**: Bevat transacties met ID, datum, omschrijving, bedrag, mededelingen
- **`categories`**: Bevat categorieën met id, catname (naam)
- **`categorie_mutatie`**: Linkt mutaties (transacties) aan categorieën via mutatieid → catid

### Database structuur (Munney)

- **`transaction`**: Bevat transacties met id, date, description, notes, amount, category_id
- **`category`**: Bevat categorieën met id, name, account_id

Het script leest de SQL dump direct (geen database connectie nodig naar de oude database).

## Na de migratie

Na een succesvolle migratie:

1. **Controleer de nieuwe categorieën**: Ga naar de Munney UI en bekijk de aangemaakte categorieën. Je kunt indien gewenst de kleuren of iconen aanpassen.
2. **Review toegewezen categorieën**: Controleer of de categorieën correct zijn toegewezen aan transacties.
3. **Handmatig categoriseren**: Review transacties die niet gematcht zijn en categoriseer deze handmatig.
4. **Budgetten koppelen**: Als je budgetten gebruikt, koppel dan de nieuwe categorieën aan de juiste budgetten.

### Verwacht resultaat

Met de `--create-categories` optie kun je verwachten:
- **43 nieuwe categorieën** worden aangemaakt (inclusief kleuren)
- **3,556 transacties** krijgen direct een categorie toegewezen
- **6,500 transacties** hadden geen categorie in de oude database (moet je handmatig doen)
- **940 transacties** zijn nieuw en stonden niet in de oude database (moet je handmatig doen)

## Ondersteuning

Bij problemen of vragen, check de output van het script (vooral in dry-run modus) voor gedetailleerde informatie over wat er mis gaat.
