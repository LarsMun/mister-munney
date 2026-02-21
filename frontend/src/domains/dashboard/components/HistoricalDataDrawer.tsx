// frontend/src/domains/dashboard/components/HistoricalDataDrawer.tsx

import { useEffect, useState } from 'react';
import { X, TrendingUp, Calendar, ChevronDown, ChevronUp } from 'lucide-react';
import { formatMoney, formatNumber } from '../../../shared/utils/MoneyFormat';
import { getTransactions } from '../../transactions/services/TransactionsService';
import type { Transaction } from '../../transactions/models/Transaction';
import { fetchAggregateStatistics, type AggregateStatistics, type StatisticsPeriod } from '../../categories/services/CategoryService';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

interface MonthlyData {
    month: string;
    total: number;
    transactionCount: number;
}

interface CategoryInfo {
    id: number;
    name: string;
    color: string;
    icon: string;
}

interface HistoricalData {
    category: CategoryInfo;
    history: MonthlyData[];
    totalAmount: number;
    averagePerMonth: number;
    monthCount: number;
}

interface HistoricalDataDrawerProps {
    isOpen: boolean;
    onClose: () => void;
    data: HistoricalData | null;
    isLoading: boolean;
    accountId?: number;
    isBudgetView?: boolean;
    categoryIds?: number[];
}

const PERIOD_OPTIONS: { value: StatisticsPeriod; label: string }[] = [
    { value: '6m', label: '6 mnd' },
    { value: '1y', label: '1 jaar' },
    { value: '2y', label: '2 jaar' },
    { value: '3y', label: '3 jaar' },
    { value: 'all', label: 'Alles' },
];

export default function HistoricalDataDrawer({
    isOpen,
    onClose,
    data,
    isLoading,
    accountId,
    isBudgetView = false,
    categoryIds
}: HistoricalDataDrawerProps) {
    // State for expanded months and their transactions
    const [expandedMonths, setExpandedMonths] = useState<Set<string>>(new Set());
    const [monthTransactions, setMonthTransactions] = useState<Record<string, Transaction[]>>({});
    const [loadingMonths, setLoadingMonths] = useState<Set<string>>(new Set());

    // Period selector state
    const [period, setPeriod] = useState<StatisticsPeriod>('1y');
    const [includeCurrentMonth, setIncludeCurrentMonth] = useState(false);
    const [statistics, setStatistics] = useState<AggregateStatistics | null>(null);
    const [isLoadingStats, setIsLoadingStats] = useState(false);

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
            setStatistics(null);
            setPeriod('1y');
            setIncludeCurrentMonth(false);
        }
    }, [isOpen]);

    // Fetch aggregate statistics when drawer opens or period changes
    useEffect(() => {
        if (!isOpen || !accountId || !categoryIds?.length) return;

        setIsLoadingStats(true);
        fetchAggregateStatistics(accountId, categoryIds, period, includeCurrentMonth)
            .then(setStatistics)
            .catch(err => {
                console.error('Error fetching aggregate statistics:', err);
                setStatistics(null);
            })
            .finally(() => setIsLoadingStats(false));
    }, [isOpen, accountId, categoryIds, period, includeCurrentMonth]);

    if (!isOpen) return null;

    // Always use data.history for chart and monthly list (full history from original load)
    // statistics is only used for summary cards (average/median over selected period)
    const fullHistory = data?.history ?? [];

    // Sort: chronological (ASC) for chart, reverse (DESC newest first) for monthly list
    const chartHistory = [...fullHistory].sort((a, b) => a.month.localeCompare(b.month));
    const listHistory = [...fullHistory].sort((a, b) => b.month.localeCompare(a.month));

    // Calculate max value for bar chart scaling
    const maxAmount = fullHistory.length > 0
        ? Math.max(...fullHistory.map(h => Math.abs(h.total)))
        : 0;

    const hasPeriodSelector = categoryIds && categoryIds.length > 0;

    // Toggle month expansion and fetch transactions
    const handleMonthToggle = async (month: string) => {
        const isExpanded = expandedMonths.has(month);

        if (isExpanded) {
            // Collapse
            const newExpanded = new Set(expandedMonths);
            newExpanded.delete(month);
            setExpandedMonths(newExpanded);
        } else {
            // Expand
            const newExpanded = new Set(expandedMonths);
            newExpanded.add(month);
            setExpandedMonths(newExpanded);

            // Fetch transactions if not already loaded
            if (!monthTransactions[month] && accountId && data) {
                setLoadingMonths(new Set(loadingMonths).add(month));

                try {
                    // Calculate date range for the month
                    const [year, monthNum] = month.split('-');
                    const startDate = `${year}-${monthNum}-01`;
                    const lastDay = new Date(parseInt(year), parseInt(monthNum), 0).getDate();
                    const endDate = `${year}-${monthNum}-${lastDay.toString().padStart(2, '0')}`;

                    // Fetch transactions for this month
                    const response = await getTransactions(accountId, startDate, endDate);

                    let filteredTransactions: Transaction[];

                    if (isBudgetView) {
                        // For budget view, filter by categories in this budget
                        const budgetCategoryIds = categoryIds || (data as any).budget?.categoryIds || [];
                        filteredTransactions = response.data.filter(t =>
                            t.category && budgetCategoryIds.includes(t.category.id)
                        );
                    } else {
                        // For category view, filter by category
                        const categoryId = (data as any).category?.id;
                        filteredTransactions = response.data.filter(t => t.category?.id === categoryId);
                    }

                    setMonthTransactions(prev => ({
                        ...prev,
                        [month]: filteredTransactions
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

    // Determine displayed average/median values
    const displayAverage = statistics ? parseFloat(statistics.average) : (data?.averagePerMonth ?? 0);
    const displayMedian = statistics ? parseFloat(statistics.median) : 0;
    const displayMonthCount = statistics?.monthCount ?? data?.monthCount ?? 0;

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
                <div className="flex items-center justify-between p-6 border-b border-gray-200 bg-gray-50">
                    <div className="flex items-center gap-3">
                        {data && (
                            <>
                                {isBudgetView ? (
                                    // Budget view header
                                    <>
                                        <div className="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                                            <span className="text-xl">💰</span>
                                        </div>
                                        <div>
                                            <h2 className="text-xl font-bold text-gray-900">
                                                {(data as any).budget?.name || 'Budget'}
                                            </h2>
                                            <p className="text-sm text-gray-600">Historische budgetgegevens</p>
                                        </div>
                                    </>
                                ) : (
                                    // Category view header
                                    <>
                                        <div
                                            className="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                            style={{ backgroundColor: (data as any).category?.color }}
                                        >
                                            <span className="text-white text-lg">
                                                {(data as any).category?.icon || '📁'}
                                            </span>
                                        </div>
                                        <div>
                                            <h2 className="text-xl font-bold text-gray-900">
                                                {(data as any).category?.name}
                                            </h2>
                                            <p className="text-sm text-gray-600">Historische gegevens</p>
                                        </div>
                                    </>
                                )}
                            </>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 hover:bg-gray-200 rounded-full transition-colors"
                        aria-label="Sluit drawer"
                    >
                        <X className="w-6 h-6 text-gray-600" />
                    </button>
                </div>

                {/* Period Selector */}
                {categoryIds && categoryIds.length > 0 && (
                    <div className="px-6 py-3 border-b border-gray-200 bg-white">
                        <div className="flex items-center justify-between flex-wrap gap-2">
                            <div className="flex items-center gap-1">
                                {PERIOD_OPTIONS.map(opt => (
                                    <button
                                        key={opt.value}
                                        type="button"
                                        onClick={() => setPeriod(opt.value)}
                                        className={`px-3 py-1.5 text-sm rounded-lg font-medium transition-colors ${
                                            period === opt.value
                                                ? 'bg-blue-600 text-white'
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                        }`}
                                    >
                                        {opt.label}
                                    </button>
                                ))}
                            </div>
                            <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={includeCurrentMonth}
                                    onChange={e => setIncludeCurrentMonth(e.target.checked)}
                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                Huidige maand meenemen
                            </label>
                        </div>
                    </div>
                )}

                {/* Content */}
                <div className={`overflow-y-auto ${hasPeriodSelector ? 'h-[calc(100%-88px-52px)]' : 'h-[calc(100%-88px)]'}`}>
                    {isLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                        </div>
                    ) : !data || fullHistory.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-gray-500">
                            <Calendar className="w-16 h-16 mb-4 text-gray-300" />
                            <p className="text-lg">Geen historische gegevens gevonden</p>
                        </div>
                    ) : (
                        <div className="p-6">
                            {/* Summary Cards */}
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div className="flex items-center gap-2 text-blue-600 mb-1">
                                        <TrendingUp className="w-4 h-4" />
                                        <span className="text-xs font-medium uppercase">Totaal</span>
                                    </div>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {formatMoney(Math.abs(data.totalAmount))}
                                    </p>
                                </div>
                                <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div className="flex items-center gap-2 text-green-600 mb-1">
                                        <Calendar className="w-4 h-4" />
                                        <span className="text-xs font-medium uppercase">Gem./maand</span>
                                    </div>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {isLoadingStats ? (
                                            <span className="inline-block w-20 h-7 bg-green-100 animate-pulse rounded" />
                                        ) : (
                                            formatMoney(Math.abs(displayAverage))
                                        )}
                                    </p>
                                    {statistics && (
                                        <p className="text-xs text-green-600 mt-1">
                                            ({displayMonthCount} {displayMonthCount === 1 ? 'maand' : 'maanden'})
                                        </p>
                                    )}
                                </div>
                                <div className="bg-orange-50 border border-orange-200 rounded-lg p-4">
                                    <div className="flex items-center gap-2 text-orange-600 mb-1">
                                        <TrendingUp className="w-4 h-4" />
                                        <span className="text-xs font-medium uppercase">Mediaan</span>
                                    </div>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {isLoadingStats ? (
                                            <span className="inline-block w-20 h-7 bg-orange-100 animate-pulse rounded" />
                                        ) : (
                                            formatMoney(Math.abs(displayMedian))
                                        )}
                                    </p>
                                    {statistics && (
                                        <p className="text-xs text-orange-600 mt-1">
                                            ({displayMonthCount} {displayMonthCount === 1 ? 'maand' : 'maanden'})
                                        </p>
                                    )}
                                </div>
                                <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                    <div className="flex items-center gap-2 text-purple-600 mb-1">
                                        <Calendar className="w-4 h-4" />
                                        <span className="text-xs font-medium uppercase">Periode</span>
                                    </div>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {displayMonthCount} {displayMonthCount === 1 ? 'maand' : 'maanden'}
                                    </p>
                                </div>
                            </div>

                            {/* Chart */}
                            {chartHistory.length > 0 && (
                                <div className="mb-6 bg-white border border-gray-200 rounded-lg p-4">
                                    <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                        Grafiek
                                    </h3>
                                    <ResponsiveContainer width="100%" height={300}>
                                        <BarChart
                                            data={chartHistory.map(item => {
                                                const monthDate = new Date(item.month + '-01');
                                                const monthName = monthDate.toLocaleDateString('nl-NL', {
                                                    month: 'short',
                                                    year: 'numeric'
                                                });
                                                return {
                                                    month: monthName,
                                                    amount: Math.abs(item.total),
                                                    originalTotal: item.total
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
                                                tickFormatter={(value) => `€ ${formatNumber(value, 0)}`}
                                                tick={{ fontSize: 12 }}
                                            />
                                            <Tooltip
                                                formatter={(value: any) => [formatMoney(Number(value)), 'Bedrag']}
                                                labelStyle={{ color: '#374151' }}
                                                contentStyle={{ backgroundColor: '#fff', border: '1px solid #e5e7eb', borderRadius: '0.375rem' }}
                                            />
                                            <Bar
                                                dataKey="amount"
                                                fill={isBudgetView ? '#3B82F6' : ((data as any).category?.color || '#3B82F6')}
                                                radius={[4, 4, 0, 0]}
                                            />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            )}

                            {/* Monthly Data List with Bar Chart */}
                            <div className="space-y-3">
                                <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                    Maandelijkse uitsplitsing
                                </h3>
                                {listHistory.map((monthData) => {
                                    const monthDate = new Date(monthData.month + '-01');
                                    const monthName = monthDate.toLocaleDateString('nl-NL', {
                                        month: 'long',
                                        year: 'numeric'
                                    });
                                    // For INCOME budgets, amounts are stored as negative (credit), so we invert the display
                                    const isIncomeBudget = isBudgetView && (data as any).budget?.budgetType === 'INCOME';
                                    const barWidth = maxAmount > 0
                                        ? (Math.abs(monthData.total) / maxAmount) * 100
                                        : 0;
                                    const isExpanded = expandedMonths.has(monthData.month);
                                    const isLoadingMonth = loadingMonths.has(monthData.month);
                                    const transactions = monthTransactions[monthData.month] || [];
                                    // transactionCount may not exist on statistics history
                                    const txCount = (monthData as any).transactionCount;

                                    return (
                                        <div
                                            key={monthData.month}
                                            className="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow"
                                        >
                                            {/* Month Header - Clickable */}
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
                                                        {txCount !== undefined && (
                                                            <span className="text-xs text-gray-500">
                                                                ({txCount} {txCount === 1 ? 'transactie' : 'transacties'})
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-right">
                                                        <p
                                                            className={`font-bold text-lg ${
                                                                isIncomeBudget ? 'text-green-600' : 'text-red-600'
                                                            }`}
                                                        >
                                                            {isIncomeBudget ? '+' : '-'}
                                                            {formatMoney(Math.abs(monthData.total))}
                                                        </p>
                                                    </div>
                                                </div>
                                                {/* Bar Chart */}
                                                <div className="relative h-2 bg-gray-100 rounded-full overflow-hidden">
                                                    <div
                                                        className={`absolute left-0 top-0 h-full rounded-full transition-all ${
                                                            isIncomeBudget ? 'bg-green-500' : 'bg-red-500'
                                                        }`}
                                                        style={{ width: `${barWidth}%` }}
                                                    />
                                                </div>
                                            </button>

                                            {/* Expanded Transactions */}
                                            {isExpanded && (
                                                <div className="border-t border-gray-200 bg-gray-50">
                                                    {isLoadingMonth ? (
                                                        <div className="flex items-center justify-center py-8">
                                                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
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
                                                                                {isBudgetView && transaction.category && (
                                                                                    <div className="flex items-center gap-1.5">
                                                                                        <div
                                                                                            className="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                                                                            style={{ backgroundColor: transaction.category.color }}
                                                                                        />
                                                                                        <span className="text-xs text-gray-600 font-medium">
                                                                                            {transaction.category.name}
                                                                                        </span>
                                                                                        <span className="text-gray-300 text-xs">•</span>
                                                                                    </div>
                                                                                )}
                                                                                <p className="text-xs text-gray-500">
                                                                                    {new Date(transaction.date).toLocaleDateString('nl-NL', {
                                                                                        day: 'numeric',
                                                                                        month: 'short',
                                                                                        year: 'numeric'
                                                                                    })}
                                                                                </p>
                                                                            </div>
                                                                            {transaction.notes && (
                                                                                <p className="text-xs text-gray-400 mt-1 line-clamp-1">
                                                                                    {transaction.notes}
                                                                                </p>
                                                                            )}
                                                                        </div>
                                                                        <div className="text-right flex-shrink-0">
                                                                            <p
                                                                                className={`font-semibold text-sm ${
                                                                                    transaction.transactionType === 'credit'
                                                                                        ? 'text-green-600'
                                                                                        : 'text-red-600'
                                                                                }`}
                                                                            >
                                                                                {transaction.transactionType === 'credit' ? '+' : '-'}
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
