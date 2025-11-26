# Munney - Personal Finance Management Application

A modern, full-stack personal finance management application built with Symfony and React. Track transactions, manage budgets, categorize expenses, and gain insights into your spending patterns with AI-powered features.

## Features

### 💰 Transaction Management
- Import transactions from CSV files (ING, Rabobank formats supported)
- Automatic duplicate detection
- Manual transaction entry and editing
- Support for both checking and savings accounts
- Transaction filtering and search

### 📊 Budget Management
- Create and manage monthly budgets
- Track income and expense categories
- Real-time budget progress tracking
- Historical budget analysis
- Project-based budgets for temporary expenses
- Budget period customization (1-12 months)

### 🏷️ Smart Categorization
- AI-powered automatic transaction categorization (OpenAI integration)
- Pattern-based categorization rules
- Manual category assignment
- Category statistics and insights
- Behavioral insights comparing current spend to historical median

### 📈 Analytics & Insights
- Comprehensive dashboard with financial overview
- Budget vs. actual spending comparisons
- Category-wise spending breakdown
- Monthly and period-based analysis
- Interactive charts and visualizations (Recharts)
- Sparkline trends for quick insights

### 🔐 Security
- JWT-based authentication
- hCaptcha integration for login protection
- Rate limiting on sensitive endpoints
- Secure password hashing
- CORS protection

### 🎨 Modern UI/UX
- Responsive design with Tailwind CSS
- Dark mode ready
- Smooth animations with Framer Motion
- Accessible components (Radix UI)
- Real-time feedback with React Hot Toast
- Intuitive drag-and-drop file uploads

## Tech Stack

### Backend
- **Framework:** Symfony 7.2
- **Database:** MySQL 8.0
- **ORM:** Doctrine ORM
- **Authentication:** Lexik JWT Bundle
- **API Documentation:** Nelmio API Doc (OpenAPI/Swagger)
- **AI Integration:** OpenAI PHP Client
- **Email:** Symfony Mailer with Resend
- **Testing:** PHPUnit

### Frontend
- **Framework:** React 19
- **Build Tool:** Vite
- **Language:** TypeScript
- **Styling:** Tailwind CSS
- **UI Components:** Radix UI
- **Charts:** Recharts
- **Routing:** React Router v7
- **HTTP Client:** Axios
- **Animations:** Framer Motion
- **Icons:** Lucide React

### DevOps
- **Containerization:** Docker & Docker Compose
- **Web Server:** Nginx (production)
- **Reverse Proxy:** Traefik support
- **CI/CD:** GitHub Actions
- **Database Migrations:** Doctrine Migrations

## Getting Started

### Prerequisites
- Docker and Docker Compose
- Git

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/LarsMun/mister-munney.git
   cd mister-munney
   ```

2. **Copy environment files**
   ```bash
   cp .env.example .env
   cp backend/.env.example backend/.env
   ```

3. **Generate secure secrets**
   ```bash
   # Generate APP_SECRET
   openssl rand -hex 32

   # Generate MySQL passwords
   openssl rand -base64 24
   ```

4. **Update .env files with your secrets**
   - Add database passwords
   - Add APP_SECRET
   - (Optional) Add OpenAI API key for AI categorization
   - (Optional) Add hCaptcha keys for login protection

5. **Start the application**
   ```bash
   docker compose up -d
   ```

6. **Run database migrations**
   ```bash
   docker exec money-backend php bin/console doctrine:migrations:migrate
   ```

7. **Access the application**
   - Frontend: http://localhost:3000
   - Backend API: http://localhost:8787
   - API Documentation: http://localhost:8787/api/doc

### Development Setup

**Backend development:**
```bash
cd backend
composer install
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load  # Optional: Load sample data
```

**Frontend development:**
```bash
cd frontend
npm install
npm run dev
```

## Configuration

### Environment Variables

**Root `.env`:**
- `MYSQL_ROOT_PASSWORD` - MySQL root password
- `MYSQL_PASSWORD` - Application database password
- `OPENAI_API_KEY` - OpenAI API key (optional)
- `HCAPTCHA_SECRET_KEY` - hCaptcha secret (optional)

**Backend `.env`:**
- `APP_ENV` - Application environment (dev/prod)
- `APP_SECRET` - Symfony application secret
- `DATABASE_URL` - Database connection string
- `CORS_ALLOW_ORIGIN` - Allowed CORS origins
- `JWT_PASSPHRASE` - JWT signing passphrase

See `.env.example` files for complete configuration options.

## API Documentation

The API is fully documented using OpenAPI/Swagger. Access the interactive documentation at:
- Development: http://localhost:8787/api/doc
- OpenAPI JSON: http://localhost:8787/api/doc.json

## Testing

**Backend tests:**
```bash
docker exec money-backend ./vendor/bin/phpunit
```

**Frontend linting:**
```bash
cd frontend
npm run lint
```

## Deployment

The application supports deployment to production environments with:
- Docker Compose for containerized deployment
- Traefik reverse proxy integration
- Automated CI/CD via GitHub Actions
- Production-optimized builds

See `deploy/ubuntu/` for Ubuntu server deployment scripts and documentation.

## Features in Development

- Multi-user support with account sharing
- Cashflow forecasting
- External payments tracking
- Advanced behavioral insights
- Budget templates
- Recurring transaction management

## Project Structure

```
├── backend/              # Symfony backend
│   ├── src/             # Application source code
│   ├── config/          # Configuration files
│   ├── migrations/      # Database migrations
│   └── tests/           # PHPUnit tests
├── frontend/            # React frontend
│   ├── src/
│   │   ├── components/  # Reusable UI components
│   │   ├── domains/     # Feature-specific modules
│   │   └── app/         # App configuration
├── deploy/              # Deployment scripts
├── .github/             # GitHub Actions workflows
└── docker-compose.yml   # Docker configuration
```

## License

This project is proprietary software.

## Contributing

This is a personal project, but suggestions and feedback are welcome through GitHub issues.

## Acknowledgments

Built with modern technologies and best practices for personal finance management.
