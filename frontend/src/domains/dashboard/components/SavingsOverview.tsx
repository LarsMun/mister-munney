import { useState, useEffect } from 'react';
import AccountService, { SavingsAccountBalance } from '../../accounts/services/AccountService';
import { formatMoney } from '../../../shared/utils/MoneyFormat';

interface SavingsOverviewProps {
    parentAccountId: number | null;
}

export default function SavingsOverview({ parentAccountId }: SavingsOverviewProps) {
    const [savingsAccounts, setSavingsAccounts] = useState<SavingsAccountBalance[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!parentAccountId) {
            setSavingsAccounts([]);
            setIsLoading(false);
            return;
        }

        const fetchSavingsBalances = async () => {
            setIsLoading(true);
            try {
                const data = await AccountService.getSavingsBalances(parentAccountId);
                setSavingsAccounts(data);
            } catch (err) {
                console.error('Error fetching savings balances:', err);
                setError('Kon spaarrekeningen niet laden');
            } finally {
                setIsLoading(false);
            }
        };

        fetchSavingsBalances();
    }, [parentAccountId]);

    if (isLoading) {
        return (
            <div className="bg-white rounded-lg shadow p-4">
                <div className="animate-pulse">
                    <div className="h-4 bg-gray-200 rounded w-1/3 mb-2"></div>
                    <div className="h-8 bg-gray-200 rounded w-1/2"></div>
                </div>
            </div>
        );
    }

    if (error) {
        return null; // Silently fail - savings overview is not critical
    }

    if (savingsAccounts.length === 0) {
        return null; // Don't show if no savings accounts
    }

    // Calculate total savings
    const totalSavings = savingsAccounts.reduce((sum, account) => {
        return sum + (account.balance || 0);
    }, 0);

    const formatAmount = (amount: number) => formatMoney(amount);

    return (
        <div className="bg-gradient-to-br from-emerald-50 to-green-50 rounded-lg shadow-md border border-emerald-200 p-4">
            <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                    <span className="text-xl">💰</span>
                    <h3 className="text-lg font-semibold text-emerald-900">Spaargeld</h3>
                </div>
                <span className="text-xl font-bold text-emerald-700">
                    {formatAmount(totalSavings)}
                </span>
            </div>

            {savingsAccounts.length > 1 && (
                <div className="space-y-2 mt-3 pt-3 border-t border-emerald-200">
                    {savingsAccounts.map((account) => (
                        <div key={account.id} className="flex justify-between items-center text-sm">
                            <span className="text-emerald-700 truncate max-w-[60%]">
                                {account.name || account.accountNumber}
                            </span>
                            <span className="text-emerald-800 font-medium">
                                {account.balance !== null ? formatAmount(account.balance) : '-'}
                            </span>
                        </div>
                    ))}
                </div>
            )}

            {savingsAccounts.length === 1 && !savingsAccounts[0].name && (
                <p className="text-xs text-emerald-600 mt-1">
                    {savingsAccounts[0].accountNumber}
                </p>
            )}
        </div>
    );
}
