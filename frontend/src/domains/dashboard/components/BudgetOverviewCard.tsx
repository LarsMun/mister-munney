// frontend/src/domains/dashboard/components/BudgetOverviewCard.tsx

import { useState } from 'react';
import { useBudgetSummary } from '../../budgets/hooks/useBudgetSummary';
import { formatMoney } from '../../../shared/utils/MoneyFormat';
import type { BudgetSummary } from '../../budgets/models/BudgetSummary';
import type { CategoryBreakdown } from '../../budgets/models/CategoryBreakdown';
import type { Transaction } from '../../transactions/models/Transaction';
import { ArrowUpIcon, ArrowDownIcon, ArrowRightIcon, ChevronDownIcon, ChevronUpIcon, ExternalLinkIcon } from 'lucide-react';
import { getCategoryBreakdown } from '../../budgets/services/BudgetsService';
import { getTransactions } from '../../transactions/services/TransactionsService';
import { fetchCategoryHistory, type CategoryHistory } from '../../categories/services/CategoryService';
import TransactionDrawer from './TransactionDrawer';
import HistoricalDataDrawer from './HistoricalDataDrawer';

interface BudgetOverviewCardProps {
    accountId: number | null;
    monthYear: string;
}

export default function BudgetOverviewCard({ accountId, monthYear }: BudgetOverviewCardProps) {
    const { summaries, uncategorized, isLoading, error } = useBudgetSummary(accountId, monthYear);

    // Separate INCOME and EXPENSE budgets
    const incomeBudgets = summaries.filter(s => s.budgetType === 'INCOME');
    const expenseBudgets = summaries.filter(s => s.budgetType === 'EXPENSE');

    // Calculate total income from INCOME budgets
    const totalIncome = incomeBudgets.reduce((sum, s) => sum + s.spentAmount, 0);

    // Calculate total allocated and spent across all expense budgets
    const totalAllocated = expenseBudgets.reduce((sum, s) => sum + s.allocatedAmount, 0);
    const totalSpent = expenseBudgets.reduce((sum, s) => sum + s.spentAmount, 0);
    const totalPercentage = totalAllocated > 0 ? (totalSpent / totalAllocated) * 100 : 0;

    if (isLoading) {
        return (
            <div className="bg-white rounded-lg shadow p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Budget Overzicht</h2>
                <div className="flex items-center justify-center py-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="bg-white rounded-lg shadow p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Budget Overzicht</h2>
                <div className="text-center py-8">
                    <p className="text-red-600 text-sm">{error}</p>
                </div>
            </div>
        );
    }

    if (!expenseBudgets || expenseBudgets.length === 0) {
        return (
            <div className="bg-white rounded-lg shadow p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Budget Overzicht</h2>
                <div className="text-center py-8">
                    <p className="text-gray-500 text-sm">Geen uitgaven budgetten beschikbaar voor deze maand</p>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-lg shadow">
            <div className="p-6 border-b border-gray-200">
                <div className="flex items-center justify-between mb-2">
                    <h2 className="text-lg font-semibold text-gray-900">Budgetoverzicht</h2>
                    <p className="text-sm text-gray-500">
                        {new Date(monthYear + '-01').toLocaleDateString('nl-NL', {
                            month: 'long',
                            year: 'numeric'
                        })}
                    </p>
                </div>

                {/* Summary Stats */}
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                    <div className="bg-purple-50 rounded-lg p-3">
                        <p className="text-xs text-purple-600 font-medium">Besteedbaar budget</p>
                        <p className="text-lg font-bold text-purple-900">{formatMoney(totalAllocated)}</p>
                        <p className="text-xs text-purple-600">{expenseBudgets.length} budgetten actief</p>
                    </div>

                    <div className="bg-blue-50 rounded-lg p-3">
                        <p className="text-xs text-blue-600 font-medium">Gecategoriseerd en in budget</p>
                        <p className="text-lg font-bold text-blue-900">{formatMoney(totalSpent)}</p>
                        <p className="text-xs text-blue-600">van {formatMoney(totalAllocated)} ({totalPercentage.toFixed(1)}%)</p>
                    </div>

                    <div className="bg-orange-50 rounded-lg p-3">
                        <p className="text-xs text-orange-600 font-medium">Ongecategoriseerd</p>
                        <p className="text-lg font-bold text-orange-900">{formatMoney(Math.abs(uncategorized.totalAmount))}</p>
                        <p className="text-xs text-orange-600">{uncategorized.count} transacties</p>
                    </div>

                    <div className="bg-green-50 rounded-lg p-3">
                        <p className="text-xs text-green-600 font-medium">Resterend</p>
                        <p className="text-lg font-bold text-green-900">{formatMoney(totalAllocated - totalSpent - Math.abs(uncategorized.totalAmount))}</p>
                    </div>
                </div>
            </div>

            <div className="p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                {expenseBudgets.map((summary) => (
                    <BudgetSummaryItem
                        key={summary.budgetId}
                        summary={summary}
                        accountId={accountId!}
                        monthYear={monthYear}
                    />
                ))}
            </div>
        </div>
    );
}

interface BudgetSummaryItemProps {
    summary: BudgetSummary;
    accountId: number;
    monthYear: string;
}

function BudgetSummaryItem({ summary, accountId, monthYear }: BudgetSummaryItemProps) {
    const [isExpanded, setIsExpanded] = useState(false);
    const [breakdown, setBreakdown] = useState<CategoryBreakdown[]>([]);
    const [isLoadingBreakdown, setIsLoadingBreakdown] = useState(false);

    // Drawer state for transactions
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [drawerTransactions, setDrawerTransactions] = useState<Transaction[]>([]);
    const [isLoadingTransactions, setIsLoadingTransactions] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState<{ id: number; name: string; color: string } | null>(null);

    // Drawer state for historical data
    const [isHistoryDrawerOpen, setIsHistoryDrawerOpen] = useState(false);
    const [historicalData, setHistoricalData] = useState<CategoryHistory | null>(null);
    const [isLoadingHistory, setIsLoadingHistory] = useState(false);

    const handleToggle = async () => {
        if (!isExpanded && breakdown.length === 0) {
            // Fetch breakdown data on first expand
            setIsLoadingBreakdown(true);
            try {
                const data = await getCategoryBreakdown(accountId, summary.budgetId, monthYear);
                setBreakdown(data);
            } catch (error) {
                console.error('Error fetching category breakdown:', error);
            } finally {
                setIsLoadingBreakdown(false);
            }
        }
        setIsExpanded(!isExpanded);
    };

    const handleCategoryClick = async (category: CategoryBreakdown, e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

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
            // Calculate date range for the month
            const [year, month] = monthYear.split('-');
            const startDate = `${year}-${month}-01`;
            const lastDay = new Date(parseInt(year), parseInt(month), 0).getDate();
            const endDate = `${year}-${month}-${lastDay.toString().padStart(2, '0')}`;

            // Fetch transactions for this category and date range
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

        // Open drawer and fetch historical data
        setIsHistoryDrawerOpen(true);
        setIsLoadingHistory(true);

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
    const getStatusColor = (status: string) => {
        switch (status) {
            case 'excellent':
                return 'bg-green-500';
            case 'good':
                return 'bg-blue-500';
            case 'warning':
                return 'bg-yellow-500';
            case 'over':
                return 'bg-red-500';
            default:
                return 'bg-gray-500';
        }
    };

    const getTrendIcon = (direction: string) => {
        switch (direction) {
            case 'up':
                return <ArrowUpIcon className="w-4 h-4 text-red-500" />;
            case 'down':
                return <ArrowDownIcon className="w-4 h-4 text-green-500" />;
            case 'stable':
                return <ArrowRightIcon className="w-4 h-4 text-gray-400" />;
            default:
                return null;
        }
    };

    const getTrendText = (direction: string, percentage: number) => {
        if (direction === 'stable') {
            return 'Stabiel t.o.v. trend';
        }
        const absPercentage = Math.abs(percentage);
        if (direction === 'up') {
            return `${absPercentage.toFixed(1)}% meer dan trend`;
        }
        return `${absPercentage.toFixed(1)}% minder dan trend`;
    };

    // Clamp percentage at 100 for visual representation, but show actual in text
    const displayPercentage = Math.min(summary.spentPercentage, 100);
    const isOverBudget = summary.spentPercentage > 100;

    return (
        <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div
                className="p-4 hover:bg-gray-50 transition-colors cursor-pointer"
                onClick={handleToggle}
            >
                {/* Header */}
                <div className="mb-3 flex items-start justify-between">
                    <div className="flex-1">
                        <h3 className="font-semibold text-gray-900 text-sm truncate" title={summary.budgetName}>
                            {summary.budgetName}
                        </h3>
                        <p className="text-xs text-gray-500 mt-0.5">
                            {summary.categoryCount} {summary.categoryCount === 1 ? 'categorie' : 'categorieën'}
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
                    >
                        {isExpanded ? (
                            <ChevronUpIcon className="w-4 h-4 text-gray-600" />
                        ) : (
                            <ChevronDownIcon className="w-4 h-4 text-gray-600" />
                        )}
                    </button>
                </div>

            {/* Percentage & Amount */}
            <div className="mb-3 text-center">
                <div className={`text-3xl font-bold mb-1 ${isOverBudget ? 'text-red-600' : 'text-gray-900'}`}>
                    {summary.spentPercentage.toFixed(0)}%
                </div>
                <div className="text-xs text-gray-600">
                    {formatMoney(summary.spentAmount)}
                </div>
                <div className="text-xs text-gray-500">
                    van {formatMoney(summary.allocatedAmount)}
                </div>
            </div>

            {/* Progress Bar */}
            <div className="mb-3">
                <div className="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div
                        className={`h-2 rounded-full transition-all duration-300 ${getStatusColor(summary.status)}`}
                        style={{ width: `${displayPercentage}%` }}
                    />
                </div>
            </div>

            {/* Remaining or Over Budget */}
            <div className="mb-2 text-center">
                {isOverBudget ? (
                    <div className="text-xs text-red-600 font-medium">
                        Over: {formatMoney(Math.abs(summary.remainingAmount))}
                    </div>
                ) : (
                    <div className="text-xs text-green-600 font-medium">
                        Resterend: {formatMoney(summary.remainingAmount)}
                    </div>
                )}
            </div>

                {/* Trend */}
                <div className="flex items-center justify-center gap-1 text-gray-600 border-t border-gray-100 pt-2">
                    {getTrendIcon(summary.trendDirection)}
                    <span className="text-xs">
                        {summary.trendDirection === 'stable' ? 'Stabiel' :
                         summary.trendDirection === 'up' ? `+${Math.abs(summary.trendPercentage).toFixed(0)}%` :
                         `-${Math.abs(summary.trendPercentage).toFixed(0)}%`}
                    </span>
                </div>
            </div>

            {/* Expandable Category Breakdown */}
            {isExpanded && (
                <div className="border-t border-gray-200 bg-gray-50 p-4">
                    {isLoadingBreakdown ? (
                        <div className="flex items-center justify-center py-4">
                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                        </div>
                    ) : breakdown.length === 0 ? (
                        <p className="text-sm text-gray-500 text-center py-2">Geen uitgaven in categorieën voor deze maand</p>
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

            {/* Transaction Drawer */}
            {selectedCategory && (
                <TransactionDrawer
                    isOpen={isDrawerOpen}
                    onClose={() => setIsDrawerOpen(false)}
                    categoryName={selectedCategory.name}
                    categoryColor={selectedCategory.color}
                    monthYear={monthYear}
                    transactions={drawerTransactions}
                    isLoading={isLoadingTransactions}
                />
            )}

            {/* Historical Data Drawer */}
            <HistoricalDataDrawer
                isOpen={isHistoryDrawerOpen}
                onClose={() => setIsHistoryDrawerOpen(false)}
                data={historicalData}
                isLoading={isLoadingHistory}
            />
        </div>
    );
}
