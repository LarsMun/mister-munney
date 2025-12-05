import { useState } from 'react';
import { OlderBudget } from '../../budgets/models/AdaptiveBudget';
import { fetchBudgetHistory, type BudgetHistory } from '../../budgets/services/BudgetsService';
import HistoricalDataDrawer from './HistoricalDataDrawer';

interface OlderBudgetsPanelProps {
    budgets: OlderBudget[];
    accountId?: number;
}

export default function OlderBudgetsPanel({ budgets, accountId }: OlderBudgetsPanelProps) {
    const [selectedBudget, setSelectedBudget] = useState<OlderBudget | null>(null);
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [isLoadingHistory, setIsLoadingHistory] = useState(false);
    const [historicalData, setHistoricalData] = useState<BudgetHistory | null>(null);

    if (budgets.length === 0) {
        return null;
    }

    const handleBudgetClick = async (budget: OlderBudget) => {
        setSelectedBudget(budget);
        setIsDrawerOpen(true);
        setIsLoadingHistory(true);

        try {
            const history = await fetchBudgetHistory(budget.id);
            setHistoricalData(history);
        } catch (error) {
            console.error('Error fetching budget history:', error);
            setHistoricalData(null);
        } finally {
            setIsLoadingHistory(false);
        }
    };

    const handleCloseDrawer = () => {
        setIsDrawerOpen(false);
        setSelectedBudget(null);
        setHistoricalData(null);
    };

    // Transform historical data for the drawer
    const drawerData = historicalData && selectedBudget ? {
        budget: {
            id: selectedBudget.id,
            name: selectedBudget.name,
            budgetType: selectedBudget.budgetType,
            categoryIds: [] // Will be populated from history if needed
        },
        history: historicalData.months.map(m => ({
            month: m.month,
            total: m.total,
            transactionCount: m.transactionCount
        })),
        totalAmount: historicalData.months.reduce((sum, m) => sum + m.total, 0),
        averagePerMonth: historicalData.months.length > 0
            ? historicalData.months.reduce((sum, m) => sum + m.total, 0) / historicalData.months.length
            : 0,
        monthCount: historicalData.months.length
    } : null;

    return (
        <>
            <details className="bg-white rounded-lg shadow">
                <summary className="cursor-pointer p-6 font-semibold text-gray-800 hover:bg-gray-50 transition-colors select-none list-none [&::-webkit-details-marker]:hidden">
                    <div className="flex items-center gap-2">
                        <span className="text-gray-400" aria-hidden="true">▶</span>
                        <span>Oudere Budgetten ({budgets.length})</span>
                    </div>
                </summary>
                <div className="p-6 pt-0 border-t border-gray-200">
                    <p className="text-sm text-gray-700 mb-4">
                        Deze budgetten hebben geen recente transacties (laatste 2 maanden) maar zijn nog actief.
                    </p>
                    <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        {budgets.map(budget => (
                            <OlderBudgetCard
                                key={budget.id}
                                budget={budget}
                                onClick={() => handleBudgetClick(budget)}
                            />
                        ))}
                    </div>
                </div>
            </details>

            <HistoricalDataDrawer
                isOpen={isDrawerOpen}
                onClose={handleCloseDrawer}
                data={drawerData}
                isLoading={isLoadingHistory}
                accountId={accountId}
                isBudgetView={true}
            />
        </>
    );
}

interface OlderBudgetCardProps {
    budget: OlderBudget;
    onClick: () => void;
}

function OlderBudgetCard({ budget, onClick }: OlderBudgetCardProps) {
    const typeLabel = budget.budgetType === 'EXPENSE' ? 'Uitgaven' : budget.budgetType === 'INCOME' ? 'Inkomsten' : 'Project';

    return (
        <button
            type="button"
            onClick={onClick}
            className="border border-gray-200 rounded-lg p-3 text-sm hover:border-blue-300 hover:shadow-md hover:bg-blue-50 transition-all text-left w-full cursor-pointer"
        >
            <p className="font-medium text-gray-900 truncate mb-1" title={budget.name}>
                {budget.name}
            </p>
            <div className="flex items-center justify-between">
                <p className="text-xs text-gray-600">
                    {budget.categoryCount} {budget.categoryCount === 1 ? 'cat.' : 'cat.'}
                </p>
                <span className="text-xs" aria-label={typeLabel} title={typeLabel}>
                    <span aria-hidden="true">
                        {budget.budgetType === 'EXPENSE' ? '💸' : budget.budgetType === 'INCOME' ? '💰' : '📋'}
                    </span>
                </span>
            </div>
        </button>
    );
}
