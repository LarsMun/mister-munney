# Cashflow Forecast - Geparkeerd Plan

## Doel
Een tijdlijn die per week/maand laat zien:
- Wat er al binnen is gekomen / uitgegeven
- Wat er nog verwacht wordt (op basis van patronen)
- Wanneer je in de min zou komen

## Voorgestelde UI

```
┌─────────────────────────────────────────────────────────┐
│  CASHFLOW FORECAST - November 2025                      │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  💰 Inkomsten                                           │
│  ├── Stortingen        €3.200 (verwacht: €3.200)  ✓    │
│  ├── Toeslagen           €180 (verwacht: €180)    ✓    │
│  └── Totaal            €3.380                          │
│                                                         │
│  💸 Uitgaven                                            │
│  ├── Vaste lasten      €1.450 (verwacht: €1.500)  ●    │
│  ├── Boodschappen        €320 (verwacht: €450)    ○    │
│  ├── Huishoudgeld        €85  (verwacht: €150)    ○    │
│  └── Totaal            €1.855 (verwacht: €2.800)       │
│                                                         │
│  ─────────────────────────────────────────────────────  │
│  📊 Resultaat                                           │
│  Actueel:    €3.380 - €1.855 = +€1.525                 │
│  Verwacht:   €3.380 - €2.800 = +€580                   │
│                                                         │
│  🏦 Spaarverkeer (niet in forecast)                    │
│  ├── Naar spaarrekening   -€500                        │
│  └── Van spaarrekening    +€0                          │
└─────────────────────────────────────────────────────────┘
```

## Belangrijke punten
- Spaarverkeer apart, telt niet mee in "kom ik uit?"
- Per budget: actueel vs verwacht (op basis van historisch patroon)
- Verwacht resultaat = wat je overhoudt aan eind van de maand

## Voorwaarde
Dit plan vereist eerst de savings-account-refactor:
- Account types (checking/savings)
- Linked accounts voor spaarrekeningen
- Transfer-detectie tussen eigen accounts

## Status
Geparkeerd - eerst savings refactor afronden
