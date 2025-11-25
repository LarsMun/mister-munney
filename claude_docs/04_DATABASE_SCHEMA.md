# Database Schema Documentation

## Entity Relationship Overview

```
┌─────────────┐       ┌──────────────┐       ┌─────────────┐
│    User     │──────▶│ AccountUser  │◀──────│   Account   │
└─────────────┘       └──────────────┘       └─────────────┘
       │                                            │
       │                                            │
       ▼                                            ▼
┌─────────────┐                             ┌─────────────┐
│LoginAttempt │                             │ Transaction │
└─────────────┘                             └─────────────┘
                                                   │
                                                   ▼
                                            ┌─────────────┐
                                            │  Category   │──┐
                                            └─────────────┘  │
                                                   ▲         │
                                                   │         │
                                            ┌──────┴──────┐  │
                                            │   Pattern   │  │
                                            └─────────────┘  │
                                                             │
                                            ┌─────────────┐  │
                                            │   Budget    │◀─┘
                                            └─────────────┘
```

## Core Entities

### User (`user`)
Authentication and user profile.

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| email | VARCHAR(180) | Unique, login identifier |
| roles | JSON | Symfony security roles |
| password | VARCHAR(255) | Hashed password |
| is_locked | TINYINT(1) | Account lock status |
| locked_at | DATETIME | When account was locked |
| unlock_token | VARCHAR(64) | Email unlock token |
| unlock_token_expires_at | DATETIME | Token expiry |

### Account (`account`)
Bank accounts tracked in the system.

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| account_number | VARCHAR(34) | IBAN (unique) |
| name | VARCHAR(255) | Display name |
| is_default | TINYINT(1) | Default account flag |

### AccountUser (`account_user`)
Many-to-many relationship with roles.

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| account_id | INT (FK) | References account |
| user_id | INT (FK) | References user |
| role | ENUM | OWNER, VIEWER, EDITOR |
| status | ENUM | ACTIVE, PENDING, REVOKED |
| created_at | DATETIME | When access was granted |
| updated_at | DATETIME | Last modification |

### Transaction (`transaction`)
Individual bank transactions.

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| account_id | INT (FK) | References account |
| category_id | INT (FK) | References category (nullable) |
| amount | BIGINT | Amount in cents |
| currency | VARCHAR(3) | ISO currency code |
| description | TEXT | Transaction description |
| counterparty | VARCHAR(255) | Other party name |
| date | DATE | Transaction date |
| import_hash | VARCHAR(64) | Duplicate detection |
| is_internal | TINYINT(1) | Internal transfer flag |

### Category (`category`)
Transaction categories (hierarchical).

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| user_id | INT (FK) | Owner of category |
| parent_id | INT (FK) | Parent category (nullable) |
| name | VARCHAR(255) | Category name |
| icon | VARCHAR(50) | Icon identifier |
| color | VARCHAR(7) | Hex color code |
| is_income | TINYINT(1) | Income category flag |

### Pattern (`pattern`)
Auto-categorization rules.

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| user_id | INT (FK) | Owner of pattern |
| category_id | INT (FK) | Target category |
| pattern | VARCHAR(255) | Match pattern |
| match_type | ENUM | CONTAINS, EXACT, REGEX |
| priority | INT | Execution order |
| is_active | TINYINT(1) | Pattern enabled |

### Budget (`budget`)
Monthly budget allocations.

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| user_id | INT (FK) | Budget owner |
| category_id | INT (FK) | Budget category |
| amount | BIGINT | Budget amount in cents |
| currency | VARCHAR(3) | ISO currency code |
| year | INT | Budget year |
| month | INT | Budget month (1-12) |

### SavingsAccount (`savings_account`)
Savings goals tracking.

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| user_id | INT (FK) | Owner |
| name | VARCHAR(255) | Goal name |
| target_amount | BIGINT | Target in cents |
| current_amount | BIGINT | Current balance |
| currency | VARCHAR(3) | ISO currency code |
| target_date | DATE | Goal date (nullable) |

### LoginAttempt (`login_attempts`)
Security audit trail.

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| email | VARCHAR(255) | Attempted email |
| attempted_at | DATETIME | Attempt timestamp |
| ip_address | VARCHAR(45) | Client IP |
| user_agent | TEXT | Browser info |
| success | TINYINT(1) | Login success flag |

### Supporting Entities

#### FeatureFlag (`feature_flag`)
Feature toggle system.

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| name | VARCHAR(100) | Flag name (unique) |
| enabled | TINYINT(1) | Global enable |
| user_ids | JSON | Enabled user IDs |

#### AiPatternSuggestion (`ai_pattern_suggestion`)
AI-generated category suggestions.

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| transaction_id | INT (FK) | Source transaction |
| suggested_category_id | INT (FK) | Suggested category |
| confidence | DECIMAL(5,4) | Confidence score |
| accepted | TINYINT(1) | User accepted |

#### ExternalPayment (`external_payment`)
External payment tracking.

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| user_id | INT (FK) | Owner |
| name | VARCHAR(255) | Payment name |
| amount | BIGINT | Amount in cents |
| due_date | DATE | Due date |

#### ProjectAttachment (`project_attachment`)
File attachments for projects.

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Auto-increment |
| filename | VARCHAR(255) | Original filename |
| path | VARCHAR(500) | Storage path |
| uploaded_at | DATETIME | Upload timestamp |

## Indexes

Key indexes for performance:

```sql
-- Transaction queries
CREATE INDEX idx_transaction_account ON transaction(account_id);
CREATE INDEX idx_transaction_date ON transaction(date);
CREATE INDEX idx_transaction_category ON transaction(category_id);
CREATE INDEX idx_transaction_hash ON transaction(import_hash);

-- User lookups
CREATE UNIQUE INDEX idx_user_email ON user(email);

-- Pattern matching
CREATE INDEX idx_pattern_user ON pattern(user_id);
CREATE INDEX idx_pattern_priority ON pattern(priority);

-- Budget queries
CREATE INDEX idx_budget_user_period ON budget(user_id, year, month);

-- Login tracking
CREATE INDEX idx_login_email_time ON login_attempts(email, attempted_at);
```

## Migration History

Migrations are stored in `backend/migrations/` and tracked via Doctrine.

Recent migrations:
- `Version20251113202926`: Login attempt tracking & account locking
- `Version20251107224624`: Budget enhancements
- `Version20251106221057`: Pattern improvements
- `Version20251105000000`: Feature flags
- `Version20251104093517`: Transaction updates
