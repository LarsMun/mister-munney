# 🎯 Code Review Opdracht: Mister Munney

## Je Rol
Je bent een **senior full-stack developer en systeem architect** met 15+ jaar ervaring in enterprise applicaties. Je hebt uitgebreide expertise in:
- Symfony & PHP best practices (PSR standaarden, SOLID principes, Domain-Driven Design)
- React & moderne frontend architectuur
- MySQL database optimalisatie en indexering
- Docker containerisatie en microservices
- Performance tuning en profiling
- Security hardening voor financiële applicaties
- Code quality en maintainability

## De Applicatie: Mister Munney

**Type:** Full-stack personal finance management systeem  
**Tech Stack:**
- Backend: Symfony (PHP)
- Frontend: React
- Database: MySQL
- Infrastructure: Docker containers (separate containers per service)

**Functionaliteit:**
- Automatische categorisatie van financiële transacties
- Budget management per categorie
- Uitgaven tracking en analyse

**API Documentatie:**
- Swagger/OpenAPI beschikbaar op: `http://localhost:8787/api/doc`

## 📋 Je Opdracht

Voer een **diepgaande, uitgebreide code audit** uit van de gehele Mister Munney codebase. Het doel is om de applicatie **robuust, lean, mean en snel** te maken.

## 🔍 Review Scope (in volgorde van prioriteit)

### 1️⃣ CODE KWALITEIT & ONDERHOUDBAARHEID
- **Symfony Backend:**
  - Naleving van Symfony best practices en coding standards
  - SOLID principes en design patterns
  - Domain-Driven Design implementatie (entities, value objects, aggregates, repositories)
  - Service layer architectuur en dependency injection
  - Code duplicatie identificeren (DRY violations)
  - Complexiteit van methods/classes (cyclomatic complexity)
  - Type declarations en PHPDoc completeness
  - Error handling en exception strategie
  
- **React Frontend:**
  - Component structuur en reusability
  - State management patterns (prop drilling, context usage, mogelijk Redux/Zustand)
  - Custom hooks en code hergebruik
  - Code duplicatie in components
  - TypeScript usage (of JS with JSDoc)
  - Folder structuur en module organisatie

### 2️⃣ PERFORMANCE OPTIMALISATIES
- **Backend Performance:**
  - Database queries (N+1 problems, missing indexes, slow queries)
  - Doctrine ORM optimalisatie (lazy vs eager loading, query builders)
  - Caching strategie (Redis/Memcached, HTTP cache, doctrine cache)
  - API response times en bottlenecks
  - Memory usage en resource leaks
  - Background job processing voor heavy operations

- **Frontend Performance:**
  - Bundle size analyse
  - Code splitting en lazy loading
  - Render performance (unnecessary re-renders, memo usage)
  - API call optimalisatie (debouncing, caching, pagination)
  - Asset optimization (images, fonts)

- **Database Performance:**
  - Index analyse (missing, unused, redundant indexes)
  - Query optimization
  - Table structure efficiency
  - Foreign key relationships

### 3️⃣ ARCHITECTUUR VERBETERINGEN
- **Overall Architecture:**
  - Separation of concerns (controllers, services, repositories)
  - Layer boundaries (presentation, application, domain, infrastructure)
  - API design (RESTful conventions, versioning, consistency)
  - Event-driven patterns waar nuttig
  - CQRS patterns voor complexe queries vs commands

- **API Review:**
  - Analyseer de Swagger docs op `http://localhost:8787/api/doc`
  - RESTful endpoint naming conventions
  - Response structure consistency
  - HTTP status code usage
  - API versioning strategie
  - Request/response validation
  - Error response standardization
  - Pagination implementation
  - Filtering en sorting patterns

- **Domain-Driven Design:**
  - Bounded contexts identificatie
  - Aggregate root design
  - Value objects vs entities
  - Domain events
  - Repository patterns

- **Docker & Infrastructure:**
  - Container configuratie optimalisatie
  - Multi-stage builds
  - Volume management
  - Network configuration
  - Environment variable management
  - Docker Compose setup

### 4️⃣ PROJECT ORGANISATIE & DOCUMENTATIE
- **File Organization:**
  - Root directory opschoning (losse files die ergens anders thuishoren)
  - Documentatie files bundelen in `/docs` folder
  - Config files logische groepering
  - Unused files identificeren en verwijderen
  - Consistent naming conventions

- **Documentatie:**
  - README completeness
  - API documentatie kwaliteit (Swagger annotations)
  - Setup instructies
  - Architecture Decision Records (ADRs)
  - Inline code comments kwaliteit

### 5️⃣ SECURITY (NON-INTRUSIVE HARDENING)
⚠️ **Belangrijk:** Focus op security verbeteringen die de user experience NIET negatief beïnvloeden.

- **Authentication & Authorization:**
  - JWT/Session management security
  - Password hashing (bcrypt/argon2)
  - Role-based access control (RBAC)
  - API authentication best practices

- **Input Validation & Sanitization:**
  - SQL injection preventie (prepared statements check)
  - XSS protection (output escaping)
  - CSRF tokens implementatie
  - Input validation op alle entry points

- **Sensitive Data Protection:**
  - Encryption at rest (voor financiële data)
  - Secure environment variable handling
  - Secrets management (geen hardcoded credentials)
  - PII data handling

- **API Security:**
  - Rate limiting
  - CORS configuration
  - HTTP security headers
  - API versioning en deprecation
  - Authentication op alle endpoints (check Swagger docs)
  - Input validation consistency

- **Dependencies:**
  - Outdated packages met bekende vulnerabilities
  - Composer/npm security audit resultaten
  - Unnecessary dependencies

### 6️⃣ AANVULLENDE ASPECTEN

- **Logging & Monitoring:**
  - Logging strategie (levels, structured logging)
  - Error tracking setup (Sentry/Bugsnag?)
  - Performance monitoring
  - Audit logging voor financiële transacties

- **Testing:**
  - Test coverage analyse
  - Unit tests kwaliteit
  - Integration tests voor kritieke flows
  - End-to-end tests voor gebruikers flows
  - Test pyramid balance
  - API tests coverage (check tegen Swagger spec)

- **Code Stability:**
  - Error-prone patterns
  - Edge case handling
  - Null/undefined handling
  - Transaction management (database transactions voor financiële operaties)
  - Data consistency checks

## 📁 Output Locatie & Structuur

**Alle rapportage bestanden moeten worden opgeslagen in:**
```
../claude_improvements/
```

**Bestandsstructuur:**
```
../claude_improvements/
├── 01_executive_summary.md
├── 02_code_quality_report.md
├── 03_performance_report.md
├── 04_architecture_report.md
├── 05_security_audit.md
├── 06_cleanup_tasks.md
└── 07_action_plan.md
```

**Zorg ervoor dat:**
- De map `../claude_improvements/` wordt aangemaakt als deze niet bestaat
- Files zijn genummerd voor logische volgorde
- Alle files lowercase met underscores gebruiken
- De extensie `.md` wordt gebruikt voor alle rapporten

## 📊 Deliverables

### 1. `01_executive_summary.md`
- High-level overview van bevindingen
- Critical issues die immediate attention nodig hebben
- Quick wins (low effort, high impact)
- Algemene health score per categorie
- API coverage & quality score (gebaseerd op Swagger)

### 2. `02_code_quality_report.md`
- Detailed code quality findings
- Duplicatie hotspots
- Complexity metrics
- Refactoring opportunities
- Concrete voorbeelden met voor/na code

### 3. `03_performance_report.md`
- Performance bottlenecks
- Database optimization opportunities
- Caching strategie aanbevelingen
- Frontend performance issues
- Meetbare improvement targets
- API response time analysis

### 4. `04_architecture_report.md`
- Architectuur diagram (current state)
- DDD assessment
- Architectuur smells
- Proposed improvements met rationale
- Migration path voor grote changes
- API architecture review (gebaseerd op Swagger docs)

### 5. `05_security_audit.md`
- Security vulnerabilities (prioritized)
- Non-intrusive hardening recommendations
- Compliance considerations (GDPR voor PII?)
- Security checklist
- API security assessment

### 6. `06_cleanup_tasks.md`
- File organization improvements
- Deprecated code removal
- Unused dependencies
- Documentation improvements
- Quick cleanup tasks

### 7. `07_action_plan.md`
- Prioritized backlog van alle improvements
- Effort estimation (S/M/L/XL)
- Dependencies tussen tasks
- Suggested sprint planning
- Technical debt reduction roadmap

## 🎯 Output Format

Voor elk issue/verbetering:
```markdown
## [CATEGORY] Issue Title
**Priority:** 🔴 Critical / 🟡 High / 🟢 Medium / ⚪ Low
**Effort:** XS / S / M / L / XL
**Impact:** High / Medium / Low

**Current Situation:**
[Beschrijf het probleem met code voorbeelden]

**Why This Matters:**
[Impact op robuustheid/snelheid/maintainability]

**Recommended Solution:**
[Concrete oplossing met code voorbeelden indien relevant]

**Implementation Notes:**
[Praktische tips voor implementatie]
```

## 🚀 Start Instructies

1. **Maak eerst de output directory aan:**
   ```bash
   mkdir -p ../claude_improvements
   ```

2. **Start de applicatie (indien nodig) om Swagger te kunnen bekijken:**
   - Zorg dat de app draait op `http://localhost:8787`
   - Open de Swagger docs op `http://localhost:8787/api/doc` voor API analyse

3. Begin met een volledige codebase scan
4. Analyseer de folder structuur eerst
5. Bekijk de API documentatie in Swagger
6. Identificeer entry points (Symfony controllers, React root)
7. Volg de belangrijkste user flows
8. Check dependencies en configurations
9. Kijk naar tests en documentatie
10. Compile je bevindingen in de rapporten in `../claude_improvements/`

**Wees kritisch maar constructief. Focus op actionable feedback. Denk als iemand die deze app production-ready moet maken voor duizenden gebruikers.**

**Bij het afronden van elk rapport, bevestig expliciet waar het bestand is opgeslagen.**
