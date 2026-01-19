import { useState, useEffect } from 'react';
import { ActiveBudget } from '../../budgets/models/AdaptiveBudget';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Cell } from 'recharts';
import { ChevronDownIcon, ChevronUpIcon, ExternalLinkIcon } from 'lucide-react';
import { getCategoryBreakdown, fetchBudgetHistory, type BudgetHistory } from '../../budgets/services/BudgetsService';
import { getTransactions } from '../../transactions/services/TransactionsService';
import { fetchCategoryHistory, type CategoryHistory } from '../../categories/services/CategoryService';
import type { CategoryBreakdown } from '../../budgets/models/CategoryBreakdown';
import type { Transaction } from '../../transactions/models/Transaction';
import TransactionDrawer from './TransactionDrawer';
import HistoricalDataDrawer from './HistoricalDataDrawer';
import { formatMoney } from '../../../shared/utils/MoneyFormat';

interface ActiveBudgetsGridProps {
    budgets: ActiveBudget[];
    startDate?: string;
    endDate?: string;
    accountId?: number;
    totalAmount?: number;
}

export default function ActiveBudgetsGrid({ budgets, startDate, endDate, accountId, totalAmount }: ActiveBudgetsGridProps) {
    if (budgets.length === 0) {
        return (
            <div className="text-center py-8 text-gray-500">
                <p>Geen actieve budgetten gevonden</p>
                <p className="text-sm mt-2">Budgetten met recente transacties worden hier weergegeven</p>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {budgets.map(budget => (
                <BudgetCardCompact
                    key={budget.id}
                    budget={budget}
                    startDate={startDate}
                    endDate={endDate}
                    accountId={accountId}
                    totalAmount={totalAmount}
                />
            ))}
        </div>
    );
}

interface BudgetCardCompactProps {
    budget: ActiveBudget;
    startDate?: string;
    endDate?: string;
    accountId?: number;
    totalAmount?: number;
}

function BudgetCardCompact({ budget, startDate, endDate, accountId, totalAmount }: BudgetCardCompactProps) {
    const { insight } = budget;

    // State for expansion and categories
    const [isExpanded, setIsExpanded] = useState(false);
    const [breakdown, setBreakdown] = useState<CategoryBreakdown[]>([]);
    const [isLoadingBreakdown, setIsLoadingBreakdown] = useState(true);

    // State for budget history (monthly data)
    const [budgetHistoryData, setBudgetHistoryData] = useState<BudgetHistory | null>(null);
    const [isLoadingBudgetHistory, setIsLoadingBudgetHistory] = useState(true);

    // Drawer state for category transactions
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [drawerTransactions, setDrawerTransactions] = useState<Transaction[]>([]);
    const [isLoadingTransactions, setIsLoadingTransactions] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState<{ id: number; name: string; color: string } | null>(null);

    // Drawer state for historical data (both category and budget)
    const [isHistoryDrawerOpen, setIsHistoryDrawerOpen] = useState(false);
    const [historicalData, setHistoricalData] = useState<CategoryHistory | BudgetHistory | null>(null);
    const [isLoadingHistory, setIsLoadingHistory] = useState(false);
    const [isBudgetHistory, setIsBudgetHistory] = useState(false);

    // Load category breakdown and budget history on mount
    useEffect(() => {
        if (!accountId || !startDate || !endDate) return;

        // Fetch category breakdown
        const loadBreakdown = async () => {
            setIsLoadingBreakdown(true);
            try {
                const data = await getCategoryBreakdown(accountId, budget.id, startDate, endDate);
                setBreakdown(data);
            } catch (error) {
                console.error('Error fetching category breakdown:', error);
            } finally {
                setIsLoadingBreakdown(false);
            }
        };

        // Fetch budget history (last 12 months)
        const loadHistory = async () => {
            setIsLoadingBudgetHistory(true);
            try {
                const data = await fetchBudgetHistory(accountId, budget.id, 12);
                setBudgetHistoryData(data);
            } catch (error) {
                console.error('Error fetching budget history:', error);
            } finally {
                setIsLoadingBudgetHistory(false);
            }
        };

        loadBreakdown();
        loadHistory();
    }, [accountId, budget.id, startDate, endDate]);

    // Check if this is an income budget to invert the sign display
    const isIncomeBudget = budget.budgetType === 'INCOME';

    // Calculate percentage of total
    const currentAmount = insight ? Math.abs(parseFloat(insight.current)) : 0;
    const percentageOfTotal = totalAmount && totalAmount > 0
        ? Math.round((currentAmount / totalAmount) * 100)
        : null;

    // Helper function to format amount (positive for INCOME budgets)
    const formatBudgetAmount = (amount: string | number | null): string => {
        if (amount === null || amount === undefined) return formatMoney(0);
        const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
        if (isIncomeBudget) {
            return formatMoney(Math.abs(numAmount));
        }
        return formatMoney(numAmount);
    };

    // Calculate comparison status
    let comparisonStatus: 'good' | 'bad' | 'neutral' = 'neutral';
    let percentageDiff = 0;
    let comparisonText = '';

    if (insight) {
        const current = parseFloat(insight.current);
        const normal = parseFloat(insight.normal);

        if (normal > 0) {
            percentageDiff = ((current - normal) / normal) * 100;

            // For expense budgets: lower is better
            if (budget.budgetType === 'EXPENSE') {
                if (percentageDiff < -10) {
                    comparisonStatus = 'good';
                    comparisonText = `${Math.abs(percentageDiff).toFixed(0)}% lager dan normaal`;
                } else if (percentageDiff > 10) {
                    comparisonStatus = 'bad';
                    comparisonText = `${percentageDiff.toFixed(0)}% hoger dan normaal`;
                } else {
                    comparisonStatus = 'neutral';
                    comparisonText = 'Ongeveer normaal';
                }
            } else {
                // For income budgets: higher is better
                if (percentageDiff > 10) {
                    comparisonStatus = 'good';
                    comparisonText = `${percentageDiff.toFixed(0)}% hoger dan normaal`;
                } else if (percentageDiff < -10) {
                    comparisonStatus = 'bad';
                    comparisonText = `${Math.abs(percentageDiff).toFixed(0)}% lager dan normaal`;
                } else {
                    comparisonStatus = 'neutral';
                    comparisonText = 'Ongeveer normaal';
                }
            }
        }
    }

    const statusColors = {
        good: 'bg-green-50 border-green-200',
        bad: 'bg-red-50 border-red-200',
        neutral: 'bg-blue-50 border-blue-200'
    };

    const statusBadgeColors = {
        good: 'bg-green-100 text-green-800',
        bad: 'bg-red-100 text-red-800',
        neutral: 'bg-gray-100 text-gray-800'
    };

    const statusIcons = {
        good: '↓',
        bad: '↑',
        neutral: '≈'
    };

    // Handler for expanding/collapsing categories
    const handleToggle = () => {
        setIsExpanded(!isExpanded);
    };

    // Handler for clicking a category to show transactions
    const handleCategoryClick = async (category: CategoryBreakdown, e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        if (!accountId || !startDate || !endDate) return;

        // Set selected category for drawer
        setSelectedCategory({
            id: category.categoryId,
            name: category.categoryName,
            color: category.categoryColor
        });

        // Open drawer and fetch transactions
        setIsDrawerOpen(true);
        setIsLoadingTransactions(true);

        try {
            // Fetch transactions for the date range
            const response = await getTransactions(accountId, startDate, endDate);

            // Filter by category
            const filteredTransactions = response.data.filter(t => t.category?.id === category.categoryId);
            setDrawerTransactions(filteredTransactions);
        } catch (error) {
            console.error('Error fetching transactions:', error);
            setDrawerTransactions([]);
        } finally {
            setIsLoadingTransactions(false);
        }
    };

    // Handler for clicking category name to show historical data
    const handleCategoryNameClick = async (category: CategoryBreakdown, e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        if (!accountId) return;

        // Open drawer and fetch historical data
        setIsHistoryDrawerOpen(true);
        setIsLoadingHistory(true);
        setIsBudgetHistory(false);

        try {
            const data = await fetchCategoryHistory(accountId, category.categoryId);
            setHistoricalData(data);
        } catch (error) {
            console.error('Error fetching category history:', error);
            setHistoricalData(null);
        } finally {
            setIsLoadingHistory(false);
        }
    };

    // Handler for clicking budget title to show budget historical data
    const handleBudgetTitleClick = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        if (!accountId) return;

        // Open historical drawer and fetch budget history
        setIsHistoryDrawerOpen(true);
        setIsLoadingHistory(true);
        setIsBudgetHistory(true);

        try {
            const data = await fetchBudgetHistory(accountId, budget.id);
            setHistoricalData(data);
        } catch (error) {
            console.error('Error fetching budget history:', error);
            setHistoricalData(null);
        } finally {
            setIsLoadingHistory(false);
        }
    };

    // Format month/year for drawer
    const monthYear = startDate ? startDate.substring(0, 7) : '';

    return (
        <article
            className="border-2 border-gray-200 bg-white rounded-lg p-4 transition-all hover:shadow-md focus-within:ring-2 focus-within:ring-blue-500 focus-within:outline-none flex flex-col"
            aria-label={`Budget ${budget.name}`}
        >
            {/* Header */}
            <div className="mb-3 flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handleBudgetTitleClick}
                            className="text-left group flex-1 min-w-0"
                            title="Bekijk alle transacties in dit budget"
                        >
                            <h4 className="font-semibold text-gray-900 text-base truncate group-hover:text-blue-600 group-hover:underline transition-colors">
                                {budget.name}
                            </h4>
                        </button>
                        {percentageOfTotal !== null && (
                            <span className="flex-shrink-0 text-sm font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                                {percentageOfTotal}%
                            </span>
                        )}
                    </div>
                    <p className="text-xs text-gray-500 mt-1">
                        {budget.categoryCount} {budget.categoryCount === 1 ? 'categorie' : 'categorieën'}
                    </p>
                </div>
                <button
                    type="button"
                    className="ml-2 p-1 hover:bg-gray-200 rounded transition-colors"
                    onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        handleToggle();
                    }}
                    title="Toon categorieën"
                >
                    {isExpanded ? (
                        <ChevronUpIcon className="w-4 h-4 text-gray-600" />
                    ) : (
                        <ChevronDownIcon className="w-4 h-4 text-gray-600" />
                    )}
                </button>
            </div>

            {insight ? (
                <>
                    {/* Main spending amount - prominent with status indicator */}
                    <div className={`mb-4 p-3 rounded-lg border ${statusColors[comparisonStatus]}`}>
                        <div className="flex items-center justify-between mb-1">
                            <div className="text-xs text-gray-600">{isIncomeBudget ? 'Ontvangen' : 'Uitgegeven'}</div>
                            {comparisonText && (
                                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${statusBadgeColors[comparisonStatus]}`}>
                                    {statusIcons[comparisonStatus]} {comparisonText}
                                </span>
                            )}
                        </div>
                        <div className="text-2xl font-bold text-gray-900">{formatBudgetAmount(insight.current)}</div>
                    </div>

                    {/* Category Distribution - List with bars (flex-grow to push chart to bottom) */}
                    <div className="flex-grow">
                    {!isLoadingBreakdown && breakdown.length > 0 && (
                        <div className="mb-4">
                            <div className="text-xs text-gray-500 mb-2">Verdeling per categorie</div>
                            <div className="space-y-2">
                                {(() => {
                                    const total = breakdown.reduce((sum, cat) => sum + Math.abs(cat.spentAmount), 0);
                                    return breakdown
                                        .filter(cat => cat.spentAmount !== 0)
                                        .sort((a, b) => Math.abs(b.spentAmount) - Math.abs(a.spentAmount))
                                        .slice(0, 5)
                                        .map((cat) => {
                                            const percentage = total > 0 ? (Math.abs(cat.spentAmount) / total) * 100 : 0;
                                            return (
                                                <div key={cat.categoryId} className="group">
                                                    <div className="flex items-center justify-between text-xs mb-0.5">
                                                        <div className="flex items-center gap-1.5 min-w-0 flex-1">
                                                            <div
                                                                className="w-2 h-2 rounded-full flex-shrink-0"
                                                                style={{ backgroundColor: cat.categoryColor }}
                                                            />
                                                            <span className="text-gray-700 truncate">{cat.categoryName}</span>
                                                        </div>
                                                        <div className="flex items-center gap-2 flex-shrink-0 ml-2">
                                                            <span className="text-gray-500 tabular-nums">{percentage.toFixed(0)}%</span>
                                                            <span className="text-gray-900 font-medium tabular-nums">{formatMoney(Math.abs(cat.spentAmount))}</span>
                                                        </div>
                                                    </div>
                                                    <div className="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                        <div
                                                            className="h-full rounded-full transition-all"
                                                            style={{
                                                                width: `${percentage}%`,
                                                                backgroundColor: cat.categoryColor
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        });
                                })()}
                                {breakdown.filter(cat => cat.spentAmount !== 0).length > 5 && (
                                    <div className="text-xs text-gray-400 text-center pt-1">
                                        +{breakdown.filter(cat => cat.spentAmount !== 0).length - 5} meer categorieën
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                    {isLoadingBreakdown && (
                        <div className="mb-4 h-20 flex items-center justify-center">
                            <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                        </div>
                    )}
                    </div>

                    {/* Monthly History Bar Chart - always at bottom */}
                    {!isLoadingBudgetHistory && budgetHistoryData && budgetHistoryData.history.length > 0 && (
                        <div>
                            <div className="text-xs text-gray-500 mb-2">Maandelijks verloop</div>
                            <div className="h-24">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart
                                        data={(() => {
                                            // Data comes DESC (newest first), take first 8 and reverse for chronological order
                                            const recentMonths = budgetHistoryData.history.slice(0, 8).reverse();
                                            return recentMonths.map(h => ({
                                                month: new Date(h.month + '-01').toLocaleDateString('nl-NL', { month: 'short' }),
                                                amount: Math.abs(h.total),
                                                fullMonth: h.month
                                            }));
                                        })()}
                                        margin={{ top: 5, right: 5, left: 5, bottom: 5 }}
                                    >
                                        <XAxis
                                            dataKey="month"
                                            tick={{ fontSize: 10, fill: '#6B7280' }}
                                            axisLine={false}
                                            tickLine={false}
                                        />
                                        <YAxis hide />
                                        <Tooltip
                                            formatter={(value: number) => [formatMoney(value), isIncomeBudget ? 'Ontvangen' : 'Uitgegeven']}
                                            labelFormatter={(label, payload) => {
                                                if (payload && payload[0]) {
                                                    const fullMonth = payload[0].payload.fullMonth;
                                                    return new Date(fullMonth + '-01').toLocaleDateString('nl-NL', { month: 'long', year: 'numeric' });
                                                }
                                                return label;
                                            }}
                                            contentStyle={{ fontSize: '12px', borderRadius: '8px' }}
                                        />
                                        <Bar dataKey="amount" radius={[4, 4, 0, 0]}>
                                            {budgetHistoryData.history.slice(0, 8).reverse().map((entry, index) => {
                                                // Highlight current month
                                                const isCurrentPeriod = startDate && entry.month === startDate.substring(0, 7);
                                                return (
                                                    <Cell
                                                        key={`cell-${index}`}
                                                        fill={isCurrentPeriod ? '#3B82F6' : '#93C5FD'}
                                                    />
                                                );
                                            })}
                                        </Bar>
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                            {/* Show average line indicator */}
                            <div className="flex items-center justify-between text-xs text-gray-500 mt-1">
                                <span>Gem: {formatMoney(budgetHistoryData.averagePerMonth)}</span>
                                <span>{budgetHistoryData.monthCount} maanden</span>
                            </div>
                        </div>
                    )}
                    {isLoadingBudgetHistory && (
                        <div className="h-24 flex items-center justify-center">
                            <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                        </div>
                    )}
                </>
            ) : (
                <div className="text-xs text-gray-600 italic">
                    Geen gegevens beschikbaar
                </div>
            )}

            {/* Expandable Category Breakdown */}
            {isExpanded && (
                <div className="mt-4 pt-4 border-t border-gray-200 bg-gray-50 -mx-4 -mb-4 px-4 pb-4 rounded-b-lg">
                    {isLoadingBreakdown ? (
                        <div className="flex items-center justify-center py-4">
                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                        </div>
                    ) : breakdown.length === 0 ? (
                        <p className="text-sm text-gray-500 text-center py-2">Geen uitgaven in categorieën voor deze periode</p>
                    ) : (
                        <div className="space-y-2">
                            <h4 className="text-xs font-semibold text-gray-700 mb-3">Uitgaven per categorie</h4>
                            {breakdown.map((category) => (
                                <div
                                    key={category.categoryId}
                                    className="flex items-center gap-3 bg-white rounded-lg p-3 hover:shadow-sm transition-shadow"
                                >
                                    <div
                                        className="w-3 h-3 rounded-full flex-shrink-0"
                                        style={{ backgroundColor: category.categoryColor }}
                                    ></div>
                                    <div className="flex-1 min-w-0">
                                        <button
                                            type="button"
                                            onClick={(e) => handleCategoryNameClick(category, e)}
                                            className="text-left w-full hover:text-blue-600 transition-colors"
                                            title="Bekijk historische gegevens"
                                        >
                                            <p className="text-sm font-medium text-gray-900 truncate hover:underline">
                                                {category.categoryName}
                                            </p>
                                        </button>
                                        <p className="text-xs text-gray-500">
                                            {category.transactionCount} {category.transactionCount === 1 ? 'transactie' : 'transacties'}
                                        </p>
                                    </div>
                                    <div className="text-right flex-shrink-0">
                                        <p className="text-sm font-semibold text-gray-900 whitespace-nowrap">
                                            {formatMoney(category.spentAmount)}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={(e) => handleCategoryClick(category, e)}
                                        className="flex-shrink-0 p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                                        title="Bekijk transacties"
                                    >
                                        <ExternalLinkIcon className="w-4 h-4" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {/* Transaction Drawer for Category transactions */}
            {selectedCategory && (
                <TransactionDrawer
                    isOpen={isDrawerOpen}
                    onClose={() => {
                        setIsDrawerOpen(false);
                        setSelectedCategory(null);
                    }}
                    categoryName={selectedCategory.name}
                    categoryColor={selectedCategory.color}
                    monthYear={monthYear}
                    transactions={drawerTransactions}
                    isLoading={isLoadingTransactions}
                />
            )}

            {/* Historical Data Drawer for both Category and Budget history */}
            <HistoricalDataDrawer
                isOpen={isHistoryDrawerOpen}
                onClose={() => {
                    setIsHistoryDrawerOpen(false);
                    setIsBudgetHistory(false);
                }}
                data={historicalData}
                isLoading={isLoadingHistory}
                accountId={accountId}
                isBudgetView={isBudgetHistory}
            />
        </article>
    );
}
