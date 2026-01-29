# Munney Frontend

React 19 + TypeScript frontend for the Munney personal finance application.

## Tech Stack

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

## Project Structure

```
src/
├── App.tsx                 # Main application with routing
├── main.tsx                # Entry point
├── app/                    # Application configuration
│   └── context/            # React contexts (Auth, Account)
├── components/             # Shared UI components
├── domains/                # Feature modules (domain-driven)
│   ├── accounts/           # Account management
│   ├── budgets/            # Budget tracking
│   ├── categories/         # Category management
│   ├── dashboard/          # Main dashboard & Sankey flow
│   ├── forecast/           # Cashflow forecasting
│   ├── patterns/           # Auto-categorization patterns
│   ├── recurring/          # Recurring transaction detection
│   └── transactions/       # Transaction management
├── lib/                    # Utilities (axios config)
├── shared/                 # Shared utilities
│   ├── components/         # Reusable components
│   ├── utils/              # Helper functions
│   └── validation/         # Form validation schemas
└── test/                   # Test setup
```

## Domain Structure

Each domain follows a consistent structure:

```
domains/{feature}/
├── {Feature}Page.tsx       # Main page component
├── index.tsx               # Route definitions
├── components/             # Feature-specific components
├── hooks/                  # Custom React hooks
├── models/                 # TypeScript types/interfaces
└── services/               # API service functions
```

## Development

```bash
# Install dependencies
npm install

# Start development server
npm run dev

# Run linting
npm run lint

# Run tests
npm run test

# Build for production
npm run build
```

## Testing

- **Unit tests:** Vitest (`npm run test`)
- **E2E tests:** Playwright (see `TESTING.md`)

## Key Patterns

### API Services
Each domain has a service file that wraps axios calls:
```typescript
// domains/transactions/services/TransactionsService.ts
export async function fetchTransactions(accountId: number): Promise<Transaction[]> {
    const response = await api.get(`/account/${accountId}/transactions`);
    return response.data;
}
```

### Custom Hooks
Data fetching is handled via custom hooks with loading/error states:
```typescript
// domains/transactions/hooks/useTransactions.ts
export function useTransactions(accountId: number) {
    const [transactions, setTransactions] = useState<Transaction[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    // ...
}
```

### Account Context
The active account is managed via React Context:
```typescript
import { useAccount } from '../app/context/AccountContext';

const { accountId, accounts } = useAccount();
```

## Environment Variables

Create a `.env` file (see `.env.example`):

```
VITE_API_URL=http://localhost:8787/api
VITE_HCAPTCHA_SITEKEY=your-sitekey
```

## Related Documentation

- `TESTING.md` - E2E testing guide
- `DASHBOARD_README.md` - Dashboard implementation details
- `../claude_docs/` - Full project documentation
