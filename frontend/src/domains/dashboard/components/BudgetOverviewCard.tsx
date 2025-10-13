// frontend/src/domains/dashboard/components/BudgetOverviewCard.tsx

import { useBudgetSummary } from '../../budgets/hooks/useBudgetSummary';
import { formatMoney } from '../../../shared/utils/MoneyFormat';
import type { BudgetSummary } from '../../budgets/models/BudgetSummary';
import { ArrowUpIcon, ArrowDownIcon, ArrowRightIcon } from 'lucide-react';

interface BudgetOverviewCardProps {
    accountId: number | null;
    monthYear: string;
}

export default function BudgetOverviewCard({ accountId, monthYear }: BudgetOverviewCardProps) {
    const { summaries, isLoading, error } = useBudgetSummary(accountId, monthYear);

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

    if (!summaries || summaries.length === 0) {
        return (
            <div className="bg-white rounded-lg shadow p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Budget Overzicht</h2>
                <div className="text-center py-8">
                    <p className="text-gray-500 text-sm">Geen budgetten beschikbaar voor deze maand</p>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-lg shadow">
            <div className="p-6 border-b border-gray-200">
                <h2 className="text-lg font-semibold text-gray-900">Budget Overzicht</h2>
                <p className="text-sm text-gray-500 mt-1">
                    {new Date(monthYear + '-01').toLocaleDateString('nl-NL', { 
                        month: 'long', 
                        year: 'numeric' 
                    })}
                </p>
            </div>

            <div className="divide-y divide-gray-100">
                {summaries.map((summary) => (
                    <BudgetSummaryItem key={summary.budgetId} summary={summary} />
                ))}
            </div>
        </div>
    );
}

interface BudgetSummaryItemProps {
    summary: BudgetSummary;
}

function BudgetSummaryItem({ summary }: BudgetSummaryItemProps) {
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
        <div className="p-6 hover:bg-gray-50 transition-colors">
            {/* Header */}
            <div className="flex items-start justify-between mb-3">
                <div>
                    <h3 className="font-medium text-gray-900">{summary.budgetName}</h3>
                    <p className="text-xs text-gray-500 mt-0.5">
                        {summary.categoryCount} {summary.categoryCount === 1 ? 'categorie' : 'categorieën'}
                    </p>
                </div>
                <div className="text-right">
                    <div className={`text-lg font-semibold ${isOverBudget ? 'text-red-600' : 'text-gray-900'}`}>
                        {summary.spentPercentage.toFixed(1)}%
                    </div>
                    <div className="text-xs text-gray-500">
                        {formatMoney(summary.spentAmount)} / {formatMoney(summary.allocatedAmount)}
                    </div>
                </div>
            </div>

            {/* Progress Bar */}
            <div className="mb-3">
                <div className="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                    <div
                        className={`h-2.5 rounded-full transition-all duration-300 ${getStatusColor(summary.status)}`}
                        style={{ width: `${displayPercentage}%` }}
                    />
                </div>
                {isOverBudget && (
                    <div className="mt-1 flex items-center gap-1 text-xs text-red-600">
                        <span className="font-medium">Over budget:</span>
                        <span>{formatMoney(Math.abs(summary.remainingAmount))}</span>
                    </div>
                )}
            </div>

            {/* Bottom Info */}
            <div className="flex items-center justify-between text-sm">
                <div className="flex items-center gap-1.5 text-gray-600">
                    {getTrendIcon(summary.trendDirection)}
                    <span className="text-xs">
                        {getTrendText(summary.trendDirection, summary.trendPercentage)}
                    </span>
                </div>
                {!isOverBudget && (
                    <div className="text-xs text-gray-500">
                        Resterend: <span className="font-medium text-gray-700">{formatMoney(summary.remainingAmount)}</span>
                    </div>
                )}
            </div>

            {/* Trend Context */}
            {summary.historicalMedian > 0 && (
                <div className="mt-2 pt-2 border-t border-gray-100">
                    <p className="text-xs text-gray-500">
                        Gemiddeld (12 mnd): <span className="font-medium text-gray-700">{formatMoney(summary.historicalMedian)}</span>
                    </p>
                </div>
            )}
        </div>
    );
}
