import { useState } from 'react';
import { RefreshCw, TrendingDown, TrendingUp, Calendar, Activity } from 'lucide-react';
import { useRecurringTransactions } from './hooks/useRecurringTransactions';
import { RecurringTransactionList } from './components/RecurringTransactionList';
import RecurringTransactionDrawer from './components/RecurringTransactionDrawer';
import type { RecurringTransaction } from './models/RecurringTransaction';

export default function RecurringPage() {
    const {
        grouped,
        summary,
        isLoading,
        error,
        isDetecting,
        updateTransaction,
        deactivateTransaction,
        detectTransactions,
    } = useRecurringTransactions();

    const [showInactive, setShowInactive] = useState(false);
    const [selectedTransaction, setSelectedTransaction] = useState<RecurringTransaction | null>(null);

    const handleDetect = async () => {
        await detectTransactions(false);
    };

    const handleForceDetect = async () => {
        if (window.confirm('Dit verwijdert alle bestaande patronen en detecteert opnieuw. Doorgaan?')) {
            await detectTransactions(true);
        }
    };

    if (isLoading) {
        return (
            <div className="max-w-5xl mx-auto">
                <div className="animate-pulse">
                    <div className="h-8 bg-gray-200 rounded w-1/3 mb-6" />
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                        {[1, 2, 3, 4].map((i) => (
                            <div key={i} className="h-24 bg-gray-200 rounded-xl" />
                        ))}
                    </div>
                    <div className="space-y-4">
                        {[1, 2, 3].map((i) => (
                            <div key={i} className="h-20 bg-gray-200 rounded-lg" />
                        ))}
                    </div>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="max-w-5xl mx-auto">
                <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                    <p className="font-medium">Er is een fout opgetreden</p>
                    <p className="text-sm">{error}</p>
                </div>
            </div>
        );
    }

    return (
        <div className="max-w-5xl mx-auto">
            {/* Header */}
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Terugkerende Transacties</h1>
                    <p className="text-gray-600 mt-1">
                        Automatisch gedetecteerde abonnementen, rekeningen en inkomsten
                    </p>
                </div>
                <div className="flex gap-2">
                    <button
                        onClick={handleDetect}
                        disabled={isDetecting}
                        className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <RefreshCw className={`w-4 h-4 ${isDetecting ? 'animate-spin' : ''}`} />
                        {isDetecting ? 'Detecteren...' : 'Detecteren'}
                    </button>
                </div>
            </div>

            {/* Summary Cards */}
            {summary && (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div className="bg-white rounded-xl border p-4">
                        <div className="flex items-center gap-2 text-gray-600 mb-2">
                            <Activity className="w-4 h-4" />
                            <span className="text-sm">Totaal</span>
                        </div>
                        <p className="text-2xl font-bold text-gray-900">{summary.total}</p>
                        <p className="text-xs text-gray-500">{summary.active} actief</p>
                    </div>

                    <div className="bg-white rounded-xl border p-4">
                        <div className="flex items-center gap-2 text-gray-600 mb-2">
                            <Calendar className="w-4 h-4" />
                            <span className="text-sm">Maandelijks</span>
                        </div>
                        <p className="text-lg font-semibold text-gray-900">
                            {grouped
                                ? grouped.monthly.filter((t) => t.isActive).length
                                : 0}{' '}
                            patronen
                        </p>
                    </div>

                    <div className="bg-white rounded-xl border p-4">
                        <div className="flex items-center gap-2 text-red-600 mb-2">
                            <TrendingDown className="w-4 h-4" />
                            <span className="text-sm">Uitgaven /maand</span>
                        </div>
                        <p className="text-2xl font-bold text-red-600">
                            {new Intl.NumberFormat('nl-NL', {
                                style: 'currency',
                                currency: 'EUR',
                            }).format(summary.monthlyDebit)}
                        </p>
                    </div>

                    <div className="bg-white rounded-xl border p-4">
                        <div className="flex items-center gap-2 text-green-600 mb-2">
                            <TrendingUp className="w-4 h-4" />
                            <span className="text-sm">Inkomsten /maand</span>
                        </div>
                        <p className="text-2xl font-bold text-green-600">
                            {new Intl.NumberFormat('nl-NL', {
                                style: 'currency',
                                currency: 'EUR',
                            }).format(summary.monthlyCredit)}
                        </p>
                    </div>
                </div>
            )}

            {/* Filter toggle */}
            <div className="flex items-center justify-between mb-4">
                <label className="flex items-center gap-2 text-sm text-gray-600">
                    <input
                        type="checkbox"
                        checked={showInactive}
                        onChange={(e) => setShowInactive(e.target.checked)}
                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    Toon gedeactiveerde
                </label>

                {summary && summary.total > 0 && (
                    <button
                        onClick={handleForceDetect}
                        disabled={isDetecting}
                        className="text-sm text-gray-500 hover:text-gray-700"
                    >
                        Opnieuw detecteren
                    </button>
                )}
            </div>

            {/* Transaction List */}
            {grouped ? (
                <RecurringTransactionList
                    grouped={
                        showInactive
                            ? grouped
                            : {
                                  weekly: grouped.weekly.filter((t) => t.isActive),
                                  biweekly: grouped.biweekly.filter((t) => t.isActive),
                                  monthly: grouped.monthly.filter((t) => t.isActive),
                                  quarterly: grouped.quarterly.filter((t) => t.isActive),
                                  yearly: grouped.yearly.filter((t) => t.isActive),
                              }
                    }
                    onUpdate={updateTransaction}
                    onDeactivate={deactivateTransaction}
                    onSelect={setSelectedTransaction}
                />
            ) : (
                <div className="text-center py-12 text-gray-500">
                    <p>Nog geen terugkerende transacties gedetecteerd.</p>
                    <p className="text-sm mt-1">
                        Klik op "Detecteren" om automatisch patronen te vinden in je transacties.
                    </p>
                </div>
            )}

            {/* Transaction Detail Drawer */}
            <RecurringTransactionDrawer
                transaction={selectedTransaction}
                onClose={() => setSelectedTransaction(null)}
            />
        </div>
    );
}
