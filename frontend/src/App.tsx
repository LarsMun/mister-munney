// src/App.tsx
import { BrowserRouter, Routes, Route, Link } from 'react-router-dom';
import TransactionsModule from './domains/transactions';
import PatternModule from './domains/patterns';
import WelcomeScreen from './components/WelcomeScreen';
import BudgetsModule from './domains/budgets';
import { useAccount } from './app/context/AccountContext';
import logo from './assets/mister-munney-logo.png';
import { Toaster } from "react-hot-toast";

export default function App() {
    const { accounts, accountId, setAccountId, hasAccounts, isLoading, refreshAccounts } = useAccount();

    // Show loading state while checking for accounts
    if (isLoading) {
        return (
            <div className="flex justify-center items-center min-h-screen">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-600">Munney wordt geladen...</p>
                </div>
            </div>
        );
    }

    // Show welcome screen if no accounts exist
    if (!hasAccounts) {
        return (
            <div className="flex justify-center items-center min-h-screen bg-gray-50">
                <WelcomeScreen onAccountCreated={refreshAccounts} />
                <Toaster position="top-center" />
            </div>
        );
    }

    return (
        <BrowserRouter>
            <Toaster position="top-center" />
            <div className="flex flex-col min-h-screen">
                {/* Header */}
                <header className="bg-blue-600 text-white p-4">
                    <div className="container mx-auto flex justify-between items-end">
                        <h1 className="text-xl font-bold">
                            <img src={logo} alt="Logo" className="h-40 w-auto" />
                        </h1>
                        <nav className="space-x-4 flex items-end">
                            <Link to="/" className="hover:underline">Home</Link>
                            <Link to="/transactions" className="hover:underline">Transacties</Link>
                            <Link to="/patterns" className="hover:underline">Patronen</Link>
                            <Link to="/budgets" className="hover:underline">Budgetten</Link>
                            {accounts.length > 1 && (
                                <select
                                    value={accountId || ''}
                                    onChange={(e) => setAccountId(Number(e.target.value))}
                                    className="text-sm bg-white text-black px-2 py-1 rounded border"
                                >
                                    {accounts.map((acc) => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.name} ({acc.accountNumber})
                                        </option>
                                    ))}
                                </select>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Main Content */}
                <main className="flex-grow container mx-auto p-6">
                    <Routes>
                        <Route path="/" element={
                            <div className="text-center py-12">
                                <h1 className="text-3xl font-bold text-gray-800 mb-4">
                                    Welkom bij Mister Munney!
                                </h1>
                                <p className="text-gray-600 mb-8">
                                    Je hebt {accounts.length} rekening{accounts.length !== 1 ? 'en' : ''} gekoppeld.
                                    Klik op "Transacties" om je financiële overzicht te bekijken.
                                </p>
                                <div className="space-x-4">
                                    <Link
                                        to="/transactions"
                                        className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors"
                                    >
                                        Bekijk Transacties
                                    </Link>
                                    <Link
                                        to="/patterns"
                                        className="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg transition-colors"
                                    >
                                        Beheer Patronen
                                    </Link>
                                </div>
                            </div>
                        } />
                        <Route path="/transactions/*" element={<TransactionsModule />} />
                        <Route path="/patterns/*" element={<PatternModule />} />
                        <Route path="/budgets/*" element={<BudgetsModule />} />
                        {/* andere routes kunnen hier */}
                    </Routes>
                </main>

                {/* Footer */}
                <footer className="bg-gray-100 text-gray-600 text-center p-4">
                    <div className="container mx-auto">
                        © {new Date().getFullYear()} Mister Munney
                    </div>
                </footer>
            </div>
        </BrowserRouter>
    );
}