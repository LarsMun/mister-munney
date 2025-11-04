import { OlderBudget } from '../../budgets/models/AdaptiveBudget';

interface OlderBudgetsPanelProps {
    budgets: OlderBudget[];
}

export default function OlderBudgetsPanel({ budgets }: OlderBudgetsPanelProps) {
    if (budgets.length === 0) {
        return null;
    }

    return (
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
                        <OlderBudgetCard key={budget.id} budget={budget} />
                    ))}
                </div>
            </div>
        </details>
    );
}

interface OlderBudgetCardProps {
    budget: OlderBudget;
}

function OlderBudgetCard({ budget }: OlderBudgetCardProps) {
    const typeLabel = budget.budgetType === 'EXPENSE' ? 'Uitgaven' : budget.budgetType === 'INCOME' ? 'Inkomsten' : 'Project';

    return (
        <article className="border border-gray-200 rounded-lg p-3 text-sm hover:border-gray-300 hover:shadow-sm transition-all">
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
        </article>
    );
}
