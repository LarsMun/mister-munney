# Mister Money - Project Overview

## Application Summary

**Mister Money (Munney)** is a personal finance management application built with a modern stack:

- **Backend**: Symfony 7.2 (PHP 8.3) with Doctrine ORM
- **Frontend**: React 19 with TypeScript, Vite, and Tailwind CSS
- **Database**: MySQL 8.0
- **Containerization**: Docker with multi-environment support

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         INFRASTRUCTURE                           │
├─────────────────────────────────────────────────────────────────┤
│  Traefik (Reverse Proxy)                                        │
│    ├── munney.munne.me (Production)                             │
│    └── devmunney.home.munne.me (Development)                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────┐    ┌─────────────────┐    ┌──────────────┐ │
│  │    Frontend     │    │     Backend     │    │   MySQL 8.0  │ │
│  │   (React 19)    │───▶│  (Symfony 7.2)  │───▶│              │ │
│  │   Port: 80/5173 │    │    Port: 80     │    │  Port: 3306  │ │
│  └─────────────────┘    └─────────────────┘    └──────────────┘ │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Directory Structure

```
money/
├── backend/                    # Symfony backend API
│   ├── src/
│   │   ├── Account/           # Account management domain
│   │   ├── Budget/            # Budget tracking domain
│   │   ├── Category/          # Transaction categories domain
│   │   ├── Command/           # CLI commands
│   │   ├── Entity/            # Doctrine entities (shared)
│   │   ├── Enum/              # PHP enums
│   │   ├── EventListener/     # Doctrine event listeners
│   │   ├── FeatureFlag/       # Feature flag system
│   │   ├── Mapper/            # DTO mappers
│   │   ├── Money/             # Money handling utilities
│   │   ├── Pattern/           # Transaction pattern matching
│   │   ├── SavingsAccount/    # Savings account domain
│   │   ├── Security/          # Authentication & authorization
│   │   ├── Transaction/       # Transaction management domain
│   │   └── User/              # User management domain
│   ├── migrations/            # Doctrine migrations
│   ├── tests/                 # PHPUnit tests
│   ├── Dockerfile             # Development Dockerfile
│   └── Dockerfile.prod        # Production Dockerfile
│
├── frontend/                   # React frontend
│   ├── src/
│   │   ├── domains/           # Feature domains
│   │   │   ├── accounts/
│   │   │   ├── budgets/
│   │   │   ├── categories/
│   │   │   ├── dashboard/
│   │   │   ├── patterns/
│   │   │   ├── savingsAccounts/
│   │   │   └── transactions/
│   │   ├── components/        # Shared UI components
│   │   ├── shared/            # Shared utilities
│   │   └── lib/               # Library code
│   ├── Dockerfile             # Development Dockerfile
│   └── Dockerfile.prod        # Production multi-stage build
│
├── deploy/ubuntu/              # Server deployment configs
│   ├── docker-compose.dev.yml
│   ├── docker-compose.prod.yml
│   ├── deploy-dev.sh
│   └── deploy-prod.sh
│
├── .github/workflows/          # GitHub Actions CI/CD
│   ├── deploy-dev.yml
│   └── deploy-prod.yml
│
├── docker-compose.yml          # Local development
└── docker-compose.prod.yml     # Root prod config (legacy)
```

## Core Features

1. **Account Management**: Bank account tracking with IBAN validation
2. **Transaction Management**: Import and categorize bank transactions (CSV)
3. **Pattern Matching**: Auto-categorize transactions based on description patterns
4. **Budget Tracking**: Monthly budgets per category with insights
5. **Savings Accounts**: Track savings goals and progress
6. **AI Integration**: OpenAI-powered category suggestions
7. **Multi-user**: Account sharing between users
8. **Security**: JWT auth, rate limiting, hCaptcha, account locking

## Technology Stack

### Backend
- PHP 8.3
- Symfony 7.2 (API Platform style REST API)
- Doctrine ORM 3.x
- LexikJWTAuthenticationBundle
- NelmioCorsBundle
- moneyphp/money for currency handling
- openai-php/client for AI features

### Frontend
- React 19 with TypeScript
- Vite 5.x for bundling
- Tailwind CSS 3.x
- Radix UI components
- React Router 7.x
- Axios for HTTP
- Recharts for visualizations

### Infrastructure
- Docker & Docker Compose
- Traefik as reverse proxy (server)
- MySQL 8.0
- Self-hosted GitHub Actions runner

## Environments

| Environment | URL | Branch | Database |
|-------------|-----|--------|----------|
| Local | localhost:3000/8787 | develop | money_db |
| Development | devmunney.home.munne.me | develop | money_db_dev |
| Production | munney.munne.me | main | money_db_prod |

## Key Entities

- **User**: Application users with authentication
- **Account**: Bank accounts (IBAN-based)
- **AccountUser**: Many-to-many with roles (owner/shared)
- **Transaction**: Individual bank transactions
- **Category**: Transaction categories (hierarchical)
- **Pattern**: Auto-categorization rules
- **Budget**: Monthly budget per category
- **SavingsAccount**: Savings goals tracking
- **LoginAttempt**: Security audit trail
