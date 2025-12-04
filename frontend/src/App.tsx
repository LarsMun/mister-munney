// src/App.tsx
import { BrowserRouter, Routes, Route, Link, NavLink, useLocation } from 'react-router-dom';
import { useState, useEffect, useRef } from 'react';
import TransactionsModule from './domains/transactions';
import PatternModule from './domains/patterns';
import WelcomeScreen from './components/WelcomeScreen';
import AuthScreen from './components/AuthScreen';
import UnlockScreen from './components/UnlockScreen';
import BudgetsModule from './domains/budgets';
import AccountManagement from './domains/accounts';
import DashboardModule from './domains/dashboard';
import CategoriesModule from './domains/categories';
import ForecastModule from './domains/forecast';
import { useAccount } from './app/context/AccountContext';
import { useAuth } from './shared/contexts/AuthContext';
import logo from './assets/mister-munney-logo.png';
import { Toaster } from "react-hot-toast";
import toast from 'react-hot-toast';
import { importTransactions } from './lib/api';
import { Download, ChevronDown, LogOut, Settings } from 'lucide-react';

function AppContent() {
    const location = useLocation();
    const { isAuthenticated, isLoading: authLoading, logout, user } = useAuth();
    const { accounts, accountId, setAccountId, hasAccounts, isLoading: accountsLoading, refreshAccounts, resetAccountState } = useAccount();
    const [showUploadModal, setShowUploadModal] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [showUserMenu, setShowUserMenu] = useState(false);
    const userMenuRef = useRef<HTMLDivElement>(null);

    // Check if we're on the unlock page
    const isUnlockPage = location.pathname === '/unlock';

    // Close user menu when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (userMenuRef.current && !userMenuRef.current.contains(event.target as Node)) {
                setShowUserMenu(false);
            }
        };

        if (showUserMenu) {
            document.addEventListener('mousedown', handleClickOutside);
        }
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [showUserMenu]);

    // Fetch accounts when user is authenticated
    useEffect(() => {
        if (isAuthenticated && !authLoading) {
            refreshAccounts();
        }
    }, [isAuthenticated, authLoading, refreshAccounts]);

    // Reset account state when user logs out
    useEffect(() => {
        if (!isAuthenticated && !authLoading) {
            resetAccountState();
        }
    }, [isAuthenticated, authLoading, resetAccountState]);

    const handleFileUpload = async (file: File) => {
        if (!accountId) {
            toast.error('Selecteer eerst een account');
            return;
        }

        setIsUploading(true);
        try {
            const result = await importTransactions(accountId, file);
            const skipped = result.duplicates ?? result.skipped ?? 0;
            toast.success(`${result.imported} transacties geïmporteerd, ${skipped} duplicaten overgeslagen`);
            setShowUploadModal(false);
        } catch (error) {
            console.error('Import failed:', error);
            toast.error('Import mislukt. Controleer het bestand en probeer opnieuw.');
        } finally {
            setIsUploading(false);
        }
    };

    // Show unlock page if on unlock route (no authentication required)
    if (isUnlockPage) {
        return (
            <>
                <UnlockScreen />
                <Toaster position="top-center" />
            </>
        );
    }

    // Show loading state while checking authentication
    if (authLoading) {
        return (
            <div className="flex justify-center items-center min-h-screen">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-600">Munney wordt geladen...</p>
                </div>
            </div>
        );
    }

    // Show login/register screen if not authenticated
    if (!isAuthenticated) {
        return (
            <>
                <AuthScreen />
                <Toaster position="top-center" />
            </>
        );
    }

    // Show loading state while checking for accounts
    if (accountsLoading) {
        return (
            <div className="flex justify-center items-center min-h-screen">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-600">Accounts worden geladen...</p>
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
        <>
            <Toaster position="top-center" />
            <div className="flex flex-col min-h-screen">
                {/* Header */}
                <header className="bg-blue-600 text-white p-4 shadow-lg">
                    <div className="container mx-auto flex justify-between items-center">
                        {/* Left: Logo */}
                        <Link to="/" className="flex items-center hover:opacity-90 transition-opacity">
                            <img src={logo} alt="Mister Munney" className="h-24 w-auto" />
                        </Link>

                        {/* Right: Navigation & User Menu */}
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
                                to="/forecast"
                                className={({ isActive }) =>
                                    `px-4 py-2 rounded-lg transition-colors font-medium ${
                                        isActive
                                            ? 'bg-white/20 text-white'
                                            : 'hover:bg-white/10'
                                    }`
                                }
                            >
                                Forecast
                            </NavLink>
                            <NavLink
                                to="/categories"
                                className={({ isActive }) =>
                                    `px-4 py-2 rounded-lg transition-colors font-medium ${
                                        isActive
                                            ? 'bg-white/20 text-white'
                                            : 'hover:bg-white/10'
                                    }`
                                }
                            >
                                Categorieën
                            </NavLink>

                            {/* Import Icon Button */}
                            <button
                                onClick={() => setShowUploadModal(true)}
                                disabled={!accountId}
                                className={`p-2 rounded-lg transition-colors ${
                                    accountId
                                        ? 'hover:bg-white/10 text-white'
                                        : 'text-white/30 cursor-not-allowed'
                                }`}
                                title="Transacties importeren"
                            >
                                <Download className="w-5 h-5" />
                            </button>

{/* User Menu Dropdown */}
                            <div className="w-px h-8 bg-white/20 mx-1"></div>
                            <div className="relative" ref={userMenuRef}>
                                <button
                                    onClick={() => setShowUserMenu(!showUserMenu)}
                                    className="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors hover:bg-white/10"
                                >
                                    <span className="text-sm font-medium">{user?.email}</span>
                                    <ChevronDown className={`w-4 h-4 transition-transform ${showUserMenu ? 'rotate-180' : ''}`} />
                                </button>

                                {showUserMenu && (
                                    <div className="absolute right-0 mt-2 w-72 bg-white rounded-lg shadow-lg py-2 z-50">
                                        {/* Account List in Dropdown */}
                                        {accounts.length > 0 && (
                                            <>
                                                <div className="px-4 py-2">
                                                    <label className="block text-xs font-medium text-gray-500 mb-2">Account</label>
                                                </div>
                                                <div className="max-h-64 overflow-y-auto">
                                                    {[...accounts].filter(a => a.type !== 'SAVINGS').sort((a, b) => {
                                                        if (a.isDefault) return -1;
                                                        if (b.isDefault) return 1;
                                                        const nameA = a.name || a.accountNumber;
                                                        const nameB = b.name || b.accountNumber;
                                                        return nameA.localeCompare(nameB);
                                                    }).map((account) => {
                                                        const isSelected = account.id === accountId;
                                                        const displayName = account.name || account.accountNumber;

                                                        return (
                                                            <button
                                                                key={account.id}
                                                                onClick={() => {
                                                                    setAccountId(account.id);
                                                                    setShowUserMenu(false);
                                                                }}
                                                                className={`w-full text-left px-4 py-2 text-sm flex items-center justify-between ${
                                                                    isSelected
                                                                        ? 'bg-blue-50 text-blue-700 font-medium'
                                                                        : 'text-gray-700 hover:bg-gray-100'
                                                                }`}
                                                            >
                                                                <span className="truncate">{displayName}</span>
                                                                {isSelected && (
                                                                    <svg className="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                                    </svg>
                                                                )}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                                <div className="border-t border-gray-200 my-1"></div>
                                            </>
                                        )}

                                        {/* Account Management Link */}
                                        <Link
                                            to="/accounts"
                                            onClick={() => setShowUserMenu(false)}
                                            className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                                        >
                                            <Settings className="w-4 h-4" />
                                            Accounts beheren
                                        </Link>

                                        {/* Divider */}
                                        <div className="border-t border-gray-200 my-1"></div>

                                        {/* Logout Button */}
                                        <button
                                            onClick={() => {
                                                logout();
                                                setShowUserMenu(false);
                                            }}
                                            className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                                        >
                                            <LogOut className="w-4 h-4" />
                                            Uitloggen
                                        </button>
                                    </div>
                                )}
                            </div>
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
                        <Route path="/categories/*" element={<CategoriesModule />} />
                        <Route path="/forecast/*" element={<ForecastModule />} />
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
        </>
    );
}

export default function App() {
    return (
        <BrowserRouter>
            <AppContent />
        </BrowserRouter>
    );
}
