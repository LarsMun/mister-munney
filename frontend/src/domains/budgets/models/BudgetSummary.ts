// frontend/src/domains/budgets/models/BudgetSummary.ts

export interface BudgetSummary {
    budgetId: number;
    budgetName: string;
    allocatedAmount: number;
    spentAmount: number;
    remainingAmount: number;
    spentPercentage: number;
    monthYear: string;
    isOverspent: boolean;
    status: 'excellent' | 'good' | 'warning' | 'over';
    trendPercentage: number;
    trendDirection: 'up' | 'down' | 'stable';
    historicalMedian: number;
    categoryCount: number;
}

export interface BudgetSummaryResponse {
    summaries: BudgetSummary[];
    monthYear: string;
}
