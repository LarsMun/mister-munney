import { useState, useEffect } from 'react';
import { X, TrendingUp, TrendingDown, Calendar, ChevronDown, ChevronUp, PiggyBank } from 'lucide-react';
import { formatMoney, formatNumber } from '../../../shared/utils/MoneyFormat';
import { Account } from '../../accounts/models/Account';
import AccountService, {
    SavingsHistoryResponse,
    SavingsTransaction
} from '../../accounts/services/AccountService';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';

interface SavingsAccountsPanelProps {
    accounts: Account[];
    checkingAccountId?: number;
}

export default function SavingsAccountsPanel({ accounts, checkingAccountId }: SavingsAccountsPanelProps) {
    const [selectedAccount, setSelectedAccount] = useState<Account | null>(null);
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [isLoadingHistory, setIsLoadingHistory] = useState(false);
    const [historyData, setHistoryData] = useState<SavingsHistoryResponse | null>(null);
    const [accountBalances, setAccountBalances] = useState<Record<number, number>>({});
    const [isLoadingBalances, setIsLoadingBalances] = useState(false);

    // Filter only savings accounts linked to the active checking account
    const savingsAccounts = accounts.filter(acc =>
        acc.type === 'SAVINGS' && acc.parentAccountId === checkingAccountId
    );

    // Fetch balances for all savings accounts on mount
    useEffect(() => {
        const fetchBalances = async () => {
            if (savingsAccounts.length === 0) return;

            setIsLoadingBalances(true);
            const balances: Record<number, number> = {};

            await Promise.all(
                savingsAccounts.map(async (account) => {
                    try {
                        const history = await AccountService.getSavingsHistory(account.id);
                        balances[account.id] = history.currentBalance;
                    } catch (error) {
                        console.error(`Error fetching balance for account ${account.id}:`, error);
                        balances[account.id] = 0;
                    }
                })
            );

            setAccountBalances(balances);
            setIsLoadingBalances(false);
        };

        fetchBalances();
    }, [checkingAccountId, accounts]);

    if (savingsAccounts.length === 0 || !checkingAccountId) {
        return null;
    }

    const handleAccountClick = async (account: Account) => {
        setSelectedAccount(account);
        setIsDrawerOpen(true);
        setIsLoadingHistory(true);

        try {
            const history = await AccountService.getSavingsHistory(account.id);
            setHistoryData(history);
            // Update balance in case it changed
            setAccountBalances(prev => ({ ...prev, [account.id]: history.currentBalance }));
        } catch (error) {
            console.error('Error fetching savings history:', error);
            setHistoryData(null);
        } finally {
            setIsLoadingHistory(false);
        }
    };

    const handleCloseDrawer = () => {
        setIsDrawerOpen(false);
        setSelectedAccount(null);
        setHistoryData(null);
    };

    return (
        <>
            <div className="bg-gradient-to-br from-purple-50 to-violet-50 rounded-lg shadow-md border-2 border-purple-200 p-6">
                <div className="flex justify-between items-center mb-4">
                    <div className="flex items-baseline gap-4">
                        <div className="flex items-center gap-2">
                            <span className="text-2xl">🏦</span>
                            <h2 className="text-2xl font-bold text-purple-900">
                                Spaarrekeningen
                            </h2>
                        </div>
                        <span className="text-sm text-purple-700 font-medium">
                            {savingsAccounts.length} {savingsAccounts.length === 1 ? 'rekening' : 'rekeningen'}
                        </span>
                        {isLoadingBalances ? (
                            <span className="text-lg font-bold text-purple-800 animate-pulse">...</span>
                        ) : (
                            <span className="text-lg font-bold text-purple-800">
                                {formatMoney(Object.values(accountBalances).reduce((sum, bal) => sum + bal, 0))}
                            </span>
                        )}
                    </div>
                </div>
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    {savingsAccounts.map(account => (
                        <SavingsAccountCard
                            key={account.id}
                            account={account}
                            balance={accountBalances[account.id]}
                            isLoadingBalance={isLoadingBalances}
                            onClick={() => handleAccountClick(account)}
                        />
                    ))}
                </div>
            </div>

            <SavingsHistoryDrawer
                isOpen={isDrawerOpen}
                onClose={handleCloseDrawer}
                account={selectedAccount}
                data={historyData}
                isLoading={isLoadingHistory}
            />
        </>
    );
}

interface SavingsAccountCardProps {
    account: Account;
    balance?: number;
    isLoadingBalance: boolean;
    onClick: () => void;
}

function SavingsAccountCard({ account, balance, isLoadingBalance, onClick }: SavingsAccountCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="bg-white border-2 border-purple-200 rounded-xl p-4 text-left hover:border-purple-400 hover:shadow-lg transition-all cursor-pointer group"
        >
            <div className="flex items-center gap-3 mb-3">
                <div className="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                    <PiggyBank className="w-5 h-5 text-purple-600" />
                </div>
                <div className="flex-1 min-w-0">
                    <p className="font-semibold text-gray-900 truncate" title={account.name || 'Spaarrekening'}>
                        {account.name || 'Spaarrekening'}
                    </p>
                </div>
            </div>
            <div className="mb-2">
                {isLoadingBalance ? (
                    <div className="h-7 bg-gray-100 rounded animate-pulse" />
                ) : (
                    <p className="text-xl font-bold text-purple-700">
                        {formatMoney(balance ?? 0)}
                    </p>
                )}
            </div>
            <div className="text-xs text-gray-500">
                {account.accountNumber}
            </div>
        </button>
    );
}

interface SavingsHistoryDrawerProps {
    isOpen: boolean;
    onClose: () => void;
    account: Account | null;
    data: SavingsHistoryResponse | null;
    isLoading: boolean;
}

function SavingsHistoryDrawer({ isOpen, onClose, account, data, isLoading }: SavingsHistoryDrawerProps) {
    const [expandedMonths, setExpandedMonths] = useState<Set<string>>(new Set());
    const [monthTransactions, setMonthTransactions] = useState<Record<string, SavingsTransaction[]>>({});
    const [loadingMonths, setLoadingMonths] = useState<Set<string>>(new Set());

    // Prevent body scroll when drawer is open
    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'unset';
        }
        return () => {
            document.body.style.overflow = 'unset';
        };
    }, [isOpen]);

    // Reset state when drawer closes
    useEffect(() => {
        if (!isOpen) {
            setExpandedMonths(new Set());
            setMonthTransactions({});
            setLoadingMonths(new Set());
        }
    }, [isOpen]);

    if (!isOpen) return null;

    const handleMonthToggle = async (month: string) => {
        const isExpanded = expandedMonths.has(month);

        if (isExpanded) {
            const newExpanded = new Set(expandedMonths);
            newExpanded.delete(month);
            setExpandedMonths(newExpanded);
        } else {
            const newExpanded = new Set(expandedMonths);
            newExpanded.add(month);
            setExpandedMonths(newExpanded);

            // Fetch transactions if not already loaded
            if (!monthTransactions[month] && account) {
                setLoadingMonths(new Set(loadingMonths).add(month));

                try {
                    const response = await AccountService.getSavingsTransactions(account.id, month);
                    setMonthTransactions(prev => ({
                        ...prev,
                        [month]: response.transactions
                    }));
                } catch (error) {
                    console.error('Error fetching transactions for month:', error);
                    setMonthTransactions(prev => ({
                        ...prev,
                        [month]: []
                    }));
                } finally {
                    const newLoading = new Set(loadingMonths);
                    newLoading.delete(month);
                    setLoadingMonths(newLoading);
                }
            }
        }
    };

    // Calculate max for bar scaling
    const maxAmount = data?.history
        ? Math.max(...data.history.map(h => Math.max(h.deposits, h.withdrawals)))
        : 0;

    return (
        <>
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-black/50 z-40 transition-opacity"
                onClick={onClose}
            />

            {/* Drawer */}
            <div
                className={`fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform transition-transform duration-300 ease-in-out ${
                    isOpen ? 'translate-x-0' : 'translate-x-full'
                }`}
            >
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b border-gray-200 bg-purple-50">
                    <div className="flex items-center gap-3">
                        <div className="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                            <PiggyBank className="w-6 h-6 text-purple-600" />
                        </div>
                        <div>
                            <h2 className="text-xl font-bold text-gray-900">
                                {account?.name || 'Spaarrekening'}
                            </h2>
                            <p className="text-sm text-gray-600">{account?.accountNumber}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 hover:bg-purple-100 rounded-full transition-colors"
                        aria-label="Sluit drawer"
                    >
                        <X className="w-6 h-6 text-gray-600" />
                    </button>
                </div>

                {/* Content */}
                <div className="overflow-y-auto h-[calc(100%-88px)]">
                    {isLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600"></div>
                        </div>
                    ) : !data || data.history.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-gray-500">
                            <Calendar className="w-16 h-16 mb-4 text-gray-300" />
                            <p className="text-lg">Geen transactiegeschiedenis gevonden</p>
                        </div>
                    ) : (
                        <div className="p-6">
                            {/* Summary Cards */}
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                    <div className="flex items-center gap-2 text-purple-600 mb-1">
                                        <PiggyBank className="w-4 h-4" />
                                        <span className="text-xs font-medium uppercase">Huidig Saldo</span>
                                    </div>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {formatMoney(data.currentBalance)}
                                    </p>
                                </div>
                                <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div className="flex items-center gap-2 text-green-600 mb-1">
                                        <TrendingUp className="w-4 h-4" />
                                        <span className="text-xs font-medium uppercase">Totaal Ingelegd</span>
                                    </div>
                                    <p className="text-2xl font-bold text-green-700">
                                        +{formatMoney(data.totalDeposits)}
                                    </p>
                                </div>
                                <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div className="flex items-center gap-2 text-red-600 mb-1">
                                        <TrendingDown className="w-4 h-4" />
                                        <span className="text-xs font-medium uppercase">Totaal Opgenomen</span>
                                    </div>
                                    <p className="text-2xl font-bold text-red-700">
                                        -{formatMoney(data.totalWithdrawals)}
                                    </p>
                                </div>
                                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div className="flex items-center gap-2 text-blue-600 mb-1">
                                        <Calendar className="w-4 h-4" />
                                        <span className="text-xs font-medium uppercase">Periode</span>
                                    </div>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {data.monthCount} {data.monthCount === 1 ? 'maand' : 'maanden'}
                                    </p>
                                </div>
                            </div>

                            {/* Chart */}
                            {data.history.length > 0 && (
                                <div className="mb-6 bg-white border border-gray-200 rounded-lg p-4">
                                    <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                        Inleg vs Opname per Maand
                                    </h3>
                                    <ResponsiveContainer width="100%" height={300}>
                                        <BarChart
                                            data={data.history.slice(0, 12).reverse().map(item => {
                                                const monthDate = new Date(item.month + '-01');
                                                const monthName = monthDate.toLocaleDateString('nl-NL', {
                                                    month: 'short',
                                                    year: 'numeric'
                                                });
                                                return {
                                                    month: monthName,
                                                    inleg: item.deposits,
                                                    opname: item.withdrawals,
                                                    netto: item.netChange
                                                };
                                            })}
                                            margin={{ top: 5, right: 30, left: 20, bottom: 5 }}
                                        >
                                            <CartesianGrid strokeDasharray="3 3" />
                                            <XAxis
                                                dataKey="month"
                                                angle={-45}
                                                textAnchor="end"
                                                height={80}
                                                tick={{ fontSize: 12 }}
                                            />
                                            <YAxis
                                                tickFormatter={(value) => `${formatNumber(value, 0)}`}
                                                tick={{ fontSize: 12 }}
                                            />
                                            <Tooltip
                                                formatter={(value: any, name: string) => [
                                                    formatMoney(Number(value)),
                                                    name === 'inleg' ? 'Inleg' : name === 'opname' ? 'Opname' : 'Netto'
                                                ]}
                                                labelStyle={{ color: '#374151' }}
                                                contentStyle={{ backgroundColor: '#fff', border: '1px solid #e5e7eb', borderRadius: '0.375rem' }}
                                            />
                                            <Legend
                                                formatter={(value) => value === 'inleg' ? 'Inleg' : value === 'opname' ? 'Opname' : 'Netto'}
                                            />
                                            <Bar dataKey="inleg" fill="#22c55e" radius={[4, 4, 0, 0]} />
                                            <Bar dataKey="opname" fill="#ef4444" radius={[4, 4, 0, 0]} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            )}

                            {/* Monthly List */}
                            <div className="space-y-3">
                                <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                    Maandelijks Overzicht
                                </h3>
                                {data.history.map((monthData) => {
                                    const monthDate = new Date(monthData.month + '-01');
                                    const monthName = monthDate.toLocaleDateString('nl-NL', {
                                        month: 'long',
                                        year: 'numeric'
                                    });
                                    const isExpanded = expandedMonths.has(monthData.month);
                                    const isLoadingMonth = loadingMonths.has(monthData.month);
                                    const transactions = monthTransactions[monthData.month] || [];
                                    const barWidthDeposits = maxAmount > 0 ? (monthData.deposits / maxAmount) * 100 : 0;
                                    const barWidthWithdrawals = maxAmount > 0 ? (monthData.withdrawals / maxAmount) * 100 : 0;

                                    return (
                                        <div
                                            key={monthData.month}
                                            className="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow"
                                        >
                                            <button
                                                type="button"
                                                onClick={() => handleMonthToggle(monthData.month)}
                                                className="w-full p-4 text-left hover:bg-gray-50 transition-colors"
                                            >
                                                <div className="flex items-center justify-between mb-2">
                                                    <div className="flex items-center gap-2 flex-1">
                                                        {isExpanded ? (
                                                            <ChevronUp className="w-5 h-5 text-gray-500" />
                                                        ) : (
                                                            <ChevronDown className="w-5 h-5 text-gray-500" />
                                                        )}
                                                        <p className="font-medium text-gray-900">{monthName}</p>
                                                        <span className="text-xs text-gray-500">
                                                            ({monthData.transactionCount} {monthData.transactionCount === 1 ? 'transactie' : 'transacties'})
                                                        </span>
                                                    </div>
                                                    <div className="text-right flex items-center gap-4">
                                                        {monthData.deposits > 0 && (
                                                            <span className="text-green-600 font-medium text-sm">
                                                                +{formatMoney(monthData.deposits)}
                                                            </span>
                                                        )}
                                                        {monthData.withdrawals > 0 && (
                                                            <span className="text-red-600 font-medium text-sm">
                                                                -{formatMoney(monthData.withdrawals)}
                                                            </span>
                                                        )}
                                                        <span className={`font-bold text-lg ${monthData.netChange >= 0 ? 'text-green-700' : 'text-red-700'}`}>
                                                            {monthData.netChange >= 0 ? '+' : ''}{formatMoney(monthData.netChange)}
                                                        </span>
                                                    </div>
                                                </div>
                                                {/* Bar Chart */}
                                                <div className="flex gap-2">
                                                    <div className="flex-1 relative h-2 bg-gray-100 rounded-full overflow-hidden">
                                                        <div
                                                            className="absolute left-0 top-0 h-full bg-green-500 rounded-full transition-all"
                                                            style={{ width: `${barWidthDeposits}%` }}
                                                        />
                                                    </div>
                                                    <div className="flex-1 relative h-2 bg-gray-100 rounded-full overflow-hidden">
                                                        <div
                                                            className="absolute left-0 top-0 h-full bg-red-500 rounded-full transition-all"
                                                            style={{ width: `${barWidthWithdrawals}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            </button>

                                            {/* Expanded Transactions */}
                                            {isExpanded && (
                                                <div className="border-t border-gray-200 bg-gray-50">
                                                    {isLoadingMonth ? (
                                                        <div className="flex items-center justify-center py-8">
                                                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
                                                        </div>
                                                    ) : transactions.length === 0 ? (
                                                        <div className="text-center py-8 text-gray-500">
                                                            <p className="text-sm">Geen transacties gevonden</p>
                                                        </div>
                                                    ) : (
                                                        <div className="p-4 space-y-2">
                                                            {transactions.map((transaction) => (
                                                                <div
                                                                    key={transaction.id}
                                                                    className="bg-white border border-gray-200 rounded-lg p-3 hover:shadow-sm transition-shadow"
                                                                >
                                                                    <div className="flex items-start justify-between">
                                                                        <div className="flex-1 min-w-0 mr-4">
                                                                            <p className="font-medium text-gray-900 truncate text-sm">
                                                                                {transaction.description}
                                                                            </p>
                                                                            <div className="flex items-center gap-2 mt-1">
                                                                                <p className="text-xs text-gray-500">
                                                                                    {new Date(transaction.date).toLocaleDateString('nl-NL', {
                                                                                        day: 'numeric',
                                                                                        month: 'short',
                                                                                        year: 'numeric'
                                                                                    })}
                                                                                </p>
                                                                                {transaction.counterpartyAccount && (
                                                                                    <>
                                                                                        <span className="text-gray-300 text-xs">•</span>
                                                                                        <p className="text-xs text-gray-400 font-mono">
                                                                                            {transaction.counterpartyAccount}
                                                                                        </p>
                                                                                    </>
                                                                                )}
                                                                            </div>
                                                                        </div>
                                                                        <div className="text-right flex-shrink-0">
                                                                            <p
                                                                                className={`font-semibold text-sm ${
                                                                                    transaction.type === 'CREDIT'
                                                                                        ? 'text-green-600'
                                                                                        : 'text-red-600'
                                                                                }`}
                                                                            >
                                                                                {transaction.type === 'CREDIT' ? '+' : '-'}
                                                                                {formatMoney(Math.abs(transaction.amount))}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
