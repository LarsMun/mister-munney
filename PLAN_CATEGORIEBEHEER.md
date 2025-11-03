# Plan: Categoriebeheer pagina

## 📊 Status tracking

**Laatst bijgewerkt**: 31 oktober 2025
**Status**: Fase 5 voltooid - Klaar voor Fase 6 (Testing & Polish)
**Voltooiing**: 83%

### Changelog

| Datum | Wijziging | Door |
|-------|-----------|------|
| 2025-10-31 | Plan aangemaakt met volledige specificatie | Claude |
| 2025-10-31 | Start met Fase 1: Backend CRUD implementatie | Claude |
| 2025-10-31 | ✅ Fase 1 voltooid: DELETE endpoint verbeterd met transactie-check, preview endpoint toegevoegd, 4 nieuwe tests | Claude |
| 2025-10-31 | ✅ Fase 2 voltooid: mergeCategories() en previewMerge() geïmplementeerd, 5 nieuwe tests (22 tests totaal, 122 assertions) | Claude |
| 2025-10-31 | ✅ Fase 3 voltooid: CategoriesPage met CategoryList en CategoryListItem, routing en navigatie toegevoegd | Claude |
| 2025-10-31 | ✅ Fase 4 voltooid: CategoryEditDialog, CategoryDeleteDialog, ColorPicker, updateCategory/deleteCategory service functies | Claude |
| 2025-10-31 | ✅ Fase 5 voltooid: CategoryMergeDialog met 2-staps wizard, merge preview, mergeCategories service functie | Claude |

### Voortgang per fase

| Fase | Status | Voltooiing | Notities |
|------|--------|-----------|----------|
| Fase 1: Backend CRUD | 🟢 Voltooid | 100% | DELETE check + preview endpoint + tests (17 tests, 93 assertions) |
| Fase 2: Backend Merge | 🟢 Voltooid | 100% | mergeCategories + previewMerge + tests (22 tests, 122 assertions) |
| Fase 3: Frontend Basis | 🟢 Voltooid | 100% | CategoriesPage, CategoryList, CategoryListItem, routing & navigatie |
| Fase 4: Frontend Edit/Delete | 🟢 Voltooid | 100% | CategoryEditDialog, CategoryDeleteDialog, ColorPicker, service functies |
| Fase 5: Frontend Merge | 🟢 Voltooid | 100% | CategoryMergeDialog met wizard, preview, merge functionaliteit |
| Fase 6: Testing & Polish | 🔴 Niet gestart | 0% | |

**Status legendes**: 🔴 Niet gestart | 🟡 In uitvoering | 🟢 Voltooid | ⚠️ Geblokkeerd

---

## Overzicht

Een nieuwe pagina waar gebruikers hun categorieën kunnen beheren, inclusief toevoegen, bewerken, verwijderen en mergen van categorieën.

## Huidige situatie

- ✅ Categorieën kunnen worden aangemaakt via comboboxen
- ❌ Geen mogelijkheid om categorieën te bewerken
- ❌ Geen mogelijkheid om categorieën te verwijderen
- ❌ Geen mogelijkheid om categorieën te mergen (transacties van categorie A naar B verplaatsen)
- ❌ Geen centraal overzicht van alle categorieën

## Gewenste functionaliteit

### 1. Categorielijst (Overzicht)
- Toon alle categorieën van het geselecteerde account
- Toon aantal transacties per categorie
- Toon totaalbedrag per categorie (laatste 12 maanden)
- Sorteerbaar op naam, aantal transacties, totaalbedrag
- Zoekfunctie om categorieën te filteren
- Visuele weergave met kleuren en iconen

### 2. Categorie aanmaken
- Formulier met:
  - Naam (verplicht)
  - Kleur (color picker)
  - Icoon (icon selector)
  - Budget (optioneel koppelen aan bestaand budget)

### 3. Categorie bewerken
- Inline editing of modal met formulier
- Wijzig naam, kleur, icoon, budget koppeling
- Preview van wijzigingen
- Direct opslaan of annuleren

### 4. Categorie verwijderen
- **Soft delete strategie**: Categorie alleen verwijderen als er GEEN transacties aan gekoppeld zijn
- Waarschuwing als er transacties gekoppeld zijn: "Deze categorie kan niet worden verwijderd omdat er X transacties aan gekoppeld zijn. Gebruik de merge functie om deze categorie samen te voegen met een andere."
- Confirmatie dialog met aantal gekoppelde transacties
- Optie om eerst te mergen en dan te verwijderen

### 5. Categorie mergen (★ Belangrijkste feature)
- **Doel**: Alle transacties van categorie A overzetten naar categorie B, en categorie A verwijderen
- **Use case**: Duplicate categorieën die ontstaan zijn tijdens migratie of handmatige invoer
- **Flow**:
  1. Selecteer bron categorie (merge van)
  2. Selecteer doel categorie (merge naar)
  3. Preview tonen:
     - Aantal transacties dat verplaatst wordt
     - Totaalbedrag
     - Impact op budgetten (indien gekoppeld)
  4. Confirmatie met warning
  5. Uitvoeren van merge operatie
  6. Bron categorie wordt verwijderd
  7. Success feedback met mogelijkheid om ongedaan te maken (binnen X seconden)

### 6. Bulk operaties
- Meerdere categorieën selecteren
- Bulk verwijderen (alleen lege categorieën)
- Bulk merge (alle geselecteerde categorieën mergen naar één categorie)
- Bulk budget toewijzing

---

## Backend Architectuur

### Nieuwe/aan te passen API endpoints

#### 1. GET `/api/account/{accountId}/categories` (bestaand - uitbreiden)
**Uitbreiden met statistieken**:
```json
{
  "id": 1,
  "name": "Boodschappen",
  "color": "#CFBFF7",
  "icon": "/backend/icons/shopping-cart.svg",
  "budget": { "id": 3, "name": "Boodschappen budget" },
  "transactionCount": 245,
  "totalAmount": "-2450.50",
  "lastUsed": "2025-10-31",
  "createdAt": "2025-01-15T10:30:00Z"
}
```

#### 2. PUT `/api/category/{id}` (bestaand - mogelijk al compleet)
**Request body**:
```json
{
  "name": "Nieuwe naam",
  "color": "#FF0000",
  "icon": "shopping-cart.svg"
}
```

#### 3. DELETE `/api/category/{id}` (nieuw of bestaand - controleren)
**Response bij gekoppelde transacties**:
```json
{
  "success": false,
  "error": "Cannot delete category with transactions",
  "transactionCount": 45,
  "canMerge": true
}
```

**Response bij succes**:
```json
{
  "success": true,
  "message": "Category deleted successfully"
}
```

#### 4. POST `/api/category/{sourceId}/merge/{targetId}` (★ NIEUW)
**Belangrijkste nieuwe endpoint**

**Request body** (optioneel):
```json
{
  "deleteSoftSource": false,
  "transferBudgetLink": true
}
```

**Response**:
```json
{
  "success": true,
  "transactionsMoved": 245,
  "sourceDeleted": true,
  "budgetTransferred": false,
  "message": "Successfully merged 245 transactions from 'Boodschappen oud' to 'Boodschappen'"
}
```

**Backend implementatie**:
```php
// In CategoryService
public function mergeCategories(
    int $sourceId,
    int $targetId,
    bool $deleteSource = true
): array
{
    // 1. Validaties
    $source = $this->categoryRepository->find($sourceId);
    $target = $this->categoryRepository->find($targetId);

    if (!$source || !$target) {
        throw new NotFoundHttpException('Category not found');
    }

    if ($source->getAccount() !== $target->getAccount()) {
        throw new BadRequestHttpException('Categories must belong to same account');
    }

    if ($sourceId === $targetId) {
        throw new BadRequestHttpException('Cannot merge category into itself');
    }

    // 2. Haal alle transacties van source op
    $transactions = $this->transactionRepository->findBy(['category' => $source]);
    $count = count($transactions);

    // 3. Update alle transacties
    foreach ($transactions as $transaction) {
        $transaction->setCategory($target);
        $this->entityManager->persist($transaction);
    }

    // 4. Verwijder source categorie
    if ($deleteSource) {
        $this->entityManager->remove($source);
    }

    // 5. Flush alle changes
    $this->entityManager->flush();

    return [
        'success' => true,
        'transactionsMoved' => $count,
        'sourceDeleted' => $deleteSource,
    ];
}
```

#### 5. GET `/api/category/{id}/preview-delete` (NIEUW)
**Geeft preview van wat er gebeurt bij verwijderen**:
```json
{
  "canDelete": false,
  "transactionCount": 45,
  "affectedBudgets": [
    { "id": 1, "name": "Maandelijks budget", "impact": "Budget link will be removed" }
  ],
  "suggestedMergeTargets": [
    { "id": 2, "name": "Boodschappen", "similarity": 0.85 }
  ]
}
```

#### 6. GET `/api/category/{id}/merge-preview/{targetId}` (NIEUW)
**Preview van merge operatie**:
```json
{
  "sourceCategory": { "id": 1, "name": "Boodschappen oud" },
  "targetCategory": { "id": 2, "name": "Boodschappen" },
  "transactionsToMove": 245,
  "totalAmount": "-2450.50",
  "dateRange": {
    "first": "2024-01-01",
    "last": "2025-10-31"
  },
  "budgetImpact": {
    "sourceBudget": null,
    "targetBudget": { "id": 3, "name": "Boodschappen budget" }
  }
}
```

---

## Frontend Architectuur

### Nieuwe pagina: `/categories`

#### Component structuur
```
frontend/src/domains/categories/
├── CategoriesPage.tsx              # Hoofdpagina
├── components/
│   ├── CategoryList.tsx            # Lijst met alle categorieën
│   ├── CategoryListItem.tsx        # Individueel categorie item
│   ├── CategoryEditDialog.tsx     # Modal voor bewerken
│   ├── CategoryDeleteDialog.tsx   # Confirmatie voor verwijderen
│   ├── CategoryMergeDialog.tsx    # ★ Merge wizard
│   ├── CategoryFormFields.tsx     # Herbruikbare form fields
│   ├── CategoryStats.tsx          # Statistieken component
│   └── IconPicker.tsx             # Icon selector component
├── hooks/
│   ├── useCategories.ts           # Hook voor CRUD operations
│   ├── useCategoryMerge.ts        # ★ Hook voor merge functionaliteit
│   └── useCategoryStats.ts        # Hook voor statistieken
└── utils/
    └── categoryUtils.ts           # Helper functies
```

### UI Design (wireframe beschrijving)

#### Layout: Tabel/Grid weergave met actieknoppen

```
┌─────────────────────────────────────────────────────────────────┐
│  Categorieën                                    [+ Nieuwe cat.]  │
├─────────────────────────────────────────────────────────────────┤
│  [Zoeken...] 🔍                    [Filter: Alle ▾] [Sort: Naam ▾]│
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ 🛒 Boodschappen                    #CFBFF7      245 trans. │ │
│  │    Budget: Maandelijks (€800/mnd)              -€2,450.50  │ │
│  │    [Bewerken] [Merge] [Verwijderen]                        │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ 🍔 Uiteten                         #7BE0AD       89 trans. │ │
│  │    Geen budget                                  -€1,234.00  │ │
│  │    [Bewerken] [Merge] [Verwijderen]                        │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ 💰 Salaris                         #FCF5C7        12 trans.│ │
│  │    Budget: Inkomsten                            +€48,000.00 │ │
│  │    [Bewerken] [Merge] [Verwijderen]                        │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

#### Merge Dialog (★ Belangrijkste UI)

```
┌─────────────────────────────────────────────────────────────┐
│  Categorieën samenvoegen                              [X]    │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  Stap 1: Selecteer bron categorie (wordt verwijderd)        │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ 🛒 Boodschappen oud                        [Geselecteerd]│ │
│  │    245 transacties  •  -€2,450.50                       │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                               │
│  Stap 2: Selecteer doel categorie (ontvangt transacties)    │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ [Zoeken of selecteer categorie...] ▾                    │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                               │
│  ✓ Geselecteerd: 🛒 Boodschappen (143 transacties)          │
│                                                               │
│  ⚠️  Preview:                                                 │
│  • 245 transacties worden verplaatst                         │
│  • Totaalbedrag: -€2,450.50                                  │
│  • Periode: 01-01-2024 t/m 31-10-2025                        │
│  • Bron categorie wordt verwijderd                           │
│  • Doel categorie krijgt 388 transacties (143 + 245)        │
│                                                               │
│  [ ] Bron categorie behouden (niet verwijderen)             │
│                                                               │
│  [Annuleren]                           [Categorieën mergen]  │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

#### Delete Dialog

```
┌─────────────────────────────────────────────────────────────┐
│  Categorie verwijderen                                [X]    │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ⚠️  Kan categorie niet verwijderen                           │
│                                                               │
│  De categorie "Boodschappen oud" heeft nog 245 transacties. │
│  Je moet eerst alle transacties verplaatsen voordat je deze │
│  categorie kunt verwijderen.                                 │
│                                                               │
│  Suggesties voor samenvoegen:                                │
│  • 🛒 Boodschappen (85% overeenkomst)                        │
│  • 🍔 Uiteten (23% overeenkomst)                             │
│                                                               │
│  [Annuleren]           [Categorieën samenvoegen →]          │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementatie Plan

### Fase 1: Backend - Basis CRUD (1-2 uur)
1. ✅ Controleer bestaande CategoryController endpoints
2. ✅ Implementeer/verbeter DELETE endpoint met transactie check
3. ✅ Implementeer PUT endpoint voor categorie updates
4. ✅ Voeg transactionCount en totalAmount toe aan GET endpoint
5. ✅ Schrijf tests voor CRUD operaties

### Fase 2: Backend - Merge functionaliteit (2-3 uur)
1. ✅ Implementeer `CategoryService::mergeCategories()`
2. ✅ Implementeer POST `/api/category/{sourceId}/merge/{targetId}`
3. ✅ Implementeer GET `/api/category/{id}/merge-preview/{targetId}`
4. ✅ Implementeer GET `/api/category/{id}/preview-delete`
5. ✅ Validaties en error handling
6. ✅ Schrijf tests voor merge functionaliteit

### Fase 3: Frontend - Basis pagina (2-3 uur) ✅ VOLTOOID
1. ✅ Maak CategoriesPage.tsx - met zoek, filter en sorteer functionaliteit
2. ✅ Implementeer CategoryList component - met empty state
3. ✅ Implementeer CategoryListItem met statistieken - expandable met trend en percentage weergave
4. ✅ Implementeer zoek en filter functionaliteit - real-time filtering en sortering op naam/aantal/bedrag
5. ✅ Routing toevoegen aan App.tsx - /categories route toegevoegd
6. ✅ Navigatie item toevoegen aan hoofdmenu - "Categorieën" menu item tussen Budgetten en Accounts

### Fase 4: Frontend - Edit/Delete (2 uur) ✅ VOLTOOID
1. ✅ CategoryEditDialog - met preview, naam/kleur/icon editing
2. ✅ CategoryDeleteDialog - met transactie-check en merge suggestie
3. ✅ IconPicker component - hergebruikt bestaande component
4. ✅ ColorPicker - nieuwe component met palette + custom color
5. ✅ Form validatie en error handling - validatie in beide dialogs
6. ✅ updateCategory() en deleteCategory() service functies toegevoegd
7. ✅ Integratie in CategoryListItem met state management

### Fase 5: Frontend - Merge functionaliteit (3-4 uur) ★ ✅ VOLTOOID
1. ✅ CategoryMergeDialog met 2-staps wizard flow (select target → preview)
2. ✅ Stap 1: Selecteer doelcategorie met zoekfunctionaliteit
3. ✅ Stap 2: Preview met transactie aantallen, bedragen, datumbereik
4. ✅ Visuele flow met bron (rood) en doel (groen) weergave
5. ✅ API integratie: GET merge-preview en POST merge endpoints
6. ✅ mergeCategories() service functie met toast notification
7. ✅ Warnings en confirmatie voor onomkeerbare actie
8. ✅ Integratie via "Samenvoegen" knop in CategoryListItem
9. ✅ Automatische refresh na merge

### Fase 6: Testing & Polish (1-2 uur)
1. ✅ End-to-end testen van alle flows
2. ✅ Edge cases testen (merge naar zichzelf, niet-bestaande categorieën, etc.)
3. ✅ Responsive design controleren
4. ✅ Accessibility (keyboard navigation, screen readers)
5. ✅ Loading states en error messages
6. ✅ Documentatie updaten

---

## Technische overwegingen

### Database transacties
- Merge operatie moet in een database transaction gebeuren
- Bij falen moet alles gerold worden
- Optimistic locking overwegen voor concurrent edits

### Performance
- Bij grote aantallen transacties (>1000) kan merge traag zijn
- Overweeg batch processing of async job voor hele grote merges
- Frontend: Virtualized list voor grote categorielijsten

### Security
- Controleer dat gebruiker eigenaar is van beide categorieën bij merge
- CSRF protectie op DELETE en POST endpoints
- Rate limiting op merge operatie

### UX verbeteringen
- Toast notifications voor success/error messages
- Optimistic updates (UI update voor API response)
- Undo functionaliteit voor merge (binnen X seconden)
- Keyboard shortcuts (Del voor delete, Ctrl+M voor merge)
- Drag & drop voor merge (sleep categorie A op B)

### Edge cases
- Wat als categorie gekoppeld is aan budget?
  - Optie geven om budget link te behouden of over te zetten
- Wat als categorieën verschillende transaction types hebben?
  - Waarschuwing tonen maar toestaan
- Wat als merge resulteert in duplicate category naam?
  - Validatie: naam moet uniek blijven binnen account

---

## Nice-to-have features (toekomstige iteraties)

### 1. Bulk operaties
- Meerdere categorieën tegelijk mergen
- Bulk delete voor lege categorieën
- Bulk color/icon update

### 2. Categorie groepen/hierarchie
- Parent-child relaties tussen categorieën
- Geneste categorieën (bijv. "Vervoer" → "Auto", "OV", "Taxi")
- Roll-up statistieken voor parent categorieën

### 3. Smart merge suggesties
- AI/ML om duplicate categorieën te detecteren
- String similarity matching (Levenshtein distance)
- Automatisch suggereren van merge candidates

### 4. Import/Export
- Export categorieën naar CSV/JSON
- Import categorieën van andere accounts
- Delen van categorieën tussen accounts

### 5. Audit log
- Historie van merge operaties
- Wie heeft wanneer welke categorie gemerged
- Mogelijkheid om merge ongedaan te maken (binnen X dagen)

### 6. Advanced statistieken
- Trend analyse per categorie
- Vergelijking tussen categorieën
- Budget vs werkelijk per categorie over tijd

---

## Acceptance criteria

### Must-have voor launch
- ✅ Gebruiker kan alle categorieën zien in een overzicht
- ✅ Gebruiker kan categorie naam, kleur en icon bewerken
- ✅ Gebruiker kan lege categorie verwijderen
- ✅ Gebruiker krijgt foutmelding bij verwijderen van categorie met transacties
- ✅ Gebruiker kan twee categorieën mergen
- ✅ Gebruiker ziet preview van merge operatie
- ✅ Gebruiker krijgt confirmatie bij merge
- ✅ Alle transacties worden correct overgezet bij merge
- ✅ Bron categorie wordt verwijderd na merge
- ✅ Gebruiker krijgt duidelijke feedback na elke actie

### Should-have
- ✅ Zoek/filter functionaliteit
- ✅ Sorteer opties (naam, aantal transacties, bedrag)
- ✅ Responsive design (mobile + desktop)
- ✅ Loading states tijdens operaties
- ✅ Error handling met duidelijke messages

### Could-have
- Smart merge suggesties
- Undo functionaliteit
- Keyboard shortcuts
- Bulk operaties
- Drag & drop merge

---

## Geschatte tijdsinvestering

| Fase | Schatting | Prioriteit |
|------|-----------|-----------|
| Backend CRUD | 1-2 uur | Must |
| Backend Merge | 2-3 uur | Must |
| Frontend Basis | 2-3 uur | Must |
| Frontend Edit/Delete | 2 uur | Must |
| Frontend Merge | 3-4 uur | Must |
| Testing & Polish | 1-2 uur | Must |
| **Totaal** | **11-16 uur** | |

---

## Volgorde van implementatie

1. **Start met Backend Merge** (belangrijkste functionaliteit)
   - Implementeer eerst de merge service logica
   - Test goed met verschillende scenario's
   - Dit is de fundering voor alles

2. **Daarna Backend CRUD**
   - Relatief simpel
   - Nodig voor basis functionaliteit

3. **Frontend Basis + Edit/Delete**
   - Basis pagina opzetten
   - Simpele operaties eerst

4. **Frontend Merge** (complex maar cruciaal)
   - Wizard flow met goede UX
   - Veel aandacht aan preview en confirmatie

5. **Polish & Testing**
   - End-to-end testen
   - Edge cases
   - UX verbeteringen

---

## Risico's en mitigaties

| Risico | Impact | Kans | Mitigatie |
|--------|--------|------|-----------|
| Merge operatie faalt halfway | Hoog | Laag | Database transactions + rollback |
| Performance bij 1000+ transacties | Medium | Medium | Batch processing, loading states |
| Concurrent edits | Medium | Laag | Optimistic locking, refresh data |
| Gebruiker merged verkeerde categorieën | Hoog | Medium | Goede preview + confirmatie, undo knop |
| Budget links gaan verloren | Medium | Medium | Budget transfer optie in merge |

---

## Conclusie

Dit plan biedt een complete categoriebeheer functionaliteit met focus op de merge feature. De implementatie is gefaseerd zodat je incrementeel kunt werken en testen. De geschatte tijd is 11-16 uur voor een volledige implementatie inclusief testing.

**Belangrijkste feature**: De merge functionaliteit, waarmee je duplicate of ongewenste categorieën kunt opruimen en alle transacties netjes kunt reorganiseren.

**Volgende stap**: Begin met het implementeren van de backend merge functionaliteit, omdat dit de kern is van de feature en de rest hierop voortbouwt.
