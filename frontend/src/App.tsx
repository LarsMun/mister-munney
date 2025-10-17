// src/App.tsx
import { BrowserRouter, Routes, Route, Link, NavLink } from 'react-router-dom';
import { useState } from 'react';
import TransactionsModule from './domains/transactions';
import PatternModule from './domains/patterns';
import WelcomeScreen from './components/WelcomeScreen';
import BudgetsModule from './domains/budgets';
import AccountManagement from './domains/accounts';
import DashboardModule from './domains/dashboard';
import { useAccount } from './app/context/AccountContext';
import AccountSelector from './shared/components/AccountSelector';
import logo from './assets/mister-munney-logo.png';
import { Toaster } from "react-hot-toast";
import toast from 'react-hot-toast';
import { importTransactions } from './lib/api';

export default function App() {
    const { accounts, accountId, setAccountId, hasAccounts, isLoading, refreshAccounts } = useAccount();
    const [showUploadModal, setShowUploadModal] = useState(false);
    const [isUploading, setIsUploading] = useState(false);

    const handleFileUpload = async (file: File) => {
        if (!accountId) {
            toast.error('Selecteer eerst een account');
            return;
        }

        setIsUploading(true);
        try {
            const result = await importTransactions(accountId, file);
            toast.success(`${result.imported} transacties geïmporteerd, ${result.duplicates} duplicaten overgeslagen`);
            setShowUploadModal(false);
        } catch (error) {
            console.error('Import failed:', error);
            toast.error('Import mislukt. Controleer het bestand en probeer opnieuw.');
        } finally {
            setIsUploading(false);
        }
    };

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
                <header className="bg-blue-600 text-white p-4 shadow-lg">
                    <div className="container mx-auto flex justify-between items-center">
                        <Link to="/" className="flex items-center hover:opacity-90 transition-opacity">
                            <img src={logo} alt="Mister Munney" className="h-24 w-auto" />
                        </Link>
                        <nav className="flex items-center gap-3">
                            <NavLink 
                                to="/" 
                                end
                                className={({ isActive }) => 
                                    `px-4 py-2 rounded-lg transition-colors font-medium ${
                                        isActive 
                                            ? 'bg-white/20 text-white' 
                                            : 'hover:bg-white/10'
                                    }`
                                }
                            >
                                Dashboard
                            </NavLink>
                            <NavLink 
                                to="/transactions"
                                className={({ isActive }) => 
                                    `px-4 py-2 rounded-lg transition-colors font-medium ${
                                        isActive 
                                            ? 'bg-white/20 text-white' 
                                            : 'hover:bg-white/10'
                                    }`
                                }
                            >
                                Transacties
                            </NavLink>
                            <NavLink 
                                to="/patterns"
                                className={({ isActive }) => 
                                    `px-4 py-2 rounded-lg transition-colors font-medium ${
                                        isActive 
                                            ? 'bg-white/20 text-white' 
                                            : 'hover:bg-white/10'
                                    }`
                                }
                            >
                                Patronen
                            </NavLink>
                            <NavLink 
                                to="/budgets"
                                className={({ isActive }) => 
                                    `px-4 py-2 rounded-lg transition-colors font-medium ${
                                        isActive 
                                            ? 'bg-white/20 text-white' 
                                            : 'hover:bg-white/10'
                                    }`
                                }
                            >
                                Budgetten
                            </NavLink>
                            <NavLink
                                to="/accounts"
                                className={({ isActive }) =>
                                    `px-4 py-2 rounded-lg transition-colors font-medium ${
                                        isActive
                                            ? 'bg-white/20 text-white'
                                            : 'hover:bg-white/10'
                                    }`
                                }
                            >
                                Accounts
                            </NavLink>

                            {/* Import Button */}
                            <button
                                onClick={() => setShowUploadModal(true)}
                                disabled={!accountId}
                                className={`px-4 py-2 rounded-lg transition-colors font-medium border ${
                                    accountId
                                        ? 'bg-white text-blue-600 border-blue-600 hover:bg-blue-50'
                                        : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed'
                                }`}
                            >
                                📤 Transacties Importeren
                            </button>

                            {accounts.length > 0 && (
                                <>
                                    <div className="w-px h-8 bg-white/20 mx-1"></div>
                                    <AccountSelector
                                        accounts={accounts}
                                        selectedAccountId={accountId}
                                        onAccountChange={setAccountId}
                                    />
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Main Content */}
                <main className="flex-grow container mx-auto p-6">
                    <Routes>
                        <Route path="/" element={<DashboardModule />} />
                        <Route path="/transactions/*" element={<TransactionsModule />} />
                        <Route path="/patterns/*" element={<PatternModule />} />
                        <Route path="/budgets/*" element={<BudgetsModule />} />
                        <Route path="/accounts" element={<AccountManagement />} />
                    </Routes>
                </main>

                {/* Footer */}
                <footer className="bg-gray-100 text-gray-600 text-center p-4">
                    <div className="container mx-auto">
                        © {new Date().getFullYear()} Mister Munney
                    </div>
                </footer>
            </div>

            {/* Upload Modal */}
            {showUploadModal && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-xl font-semibold text-gray-800">CSV Importeren</h2>
                            <button
                                onClick={() => setShowUploadModal(false)}
                                disabled={isUploading}
                                className="text-gray-500 hover:text-gray-700 text-2xl leading-none disabled:opacity-50"
                            >
                                ×
                            </button>
                        </div>
                        <div className="mb-4">
                            <p className="text-sm text-gray-600 mb-3">
                                Selecteer een CSV-bestand om transacties te importeren voor account: <span className="font-semibold">{accounts.find(a => a.id === accountId)?.name}</span>
                            </p>
                            <input
                                type="file"
                                accept=".csv"
                                disabled={isUploading}
                                onChange={(e) => {
                                    const file = e.target.files?.[0];
                                    if (file) {
                                        handleFileUpload(file);
                                    }
                                }}
                                className="block w-full text-sm text-gray-700
                                    file:mr-4 file:py-2 file:px-4
                                    file:rounded-lg file:border-0
                                    file:text-sm file:font-semibold
                                    file:bg-blue-50 file:text-blue-700
                                    hover:file:bg-blue-100
                                    cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed
                                    border border-gray-300 rounded-lg"
                            />
                        </div>
                        {isUploading && (
                            <div className="flex items-center justify-center gap-2 text-blue-600">
                                <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                                <span className="text-sm">Bezig met importeren...</span>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </BrowserRouter>
    );
}
