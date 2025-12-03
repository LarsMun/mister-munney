// frontend/src/domains/forecast/models/Forecast.ts

export interface ForecastItem {
    id: number;
    type: 'INCOME' | 'EXPENSE';
    name: string;
    icon: string | null;
    budgetId: number | null;
    categoryId: number | null;
    expectedAmount: number;
    actualAmount: number;
    position: number;
    customName: string | null;
}

export interface ForecastSummary {
    month: string; // YYYY-MM format
    incomeItems: ForecastItem[];
    expenseItems: ForecastItem[];
    totalExpectedIncome: number;
    totalActualIncome: number;
    totalExpectedExpenses: number;
    totalActualExpenses: number;
    expectedResult: number;
    actualResult: number;
    currentBalance: number;
    projectedBalance: number;
}

export interface AvailableBudget {
    id: number;
    name: string;
    icon: string | null;
    type: string;
    historicalMedian: number;
}

export interface AvailableCategory {
    id: number;
    name: string;
    icon: string | null;
    budgetId: number | null;
    budgetName: string | null;
    historicalMedian: number;
}

export interface AvailableItems {
    budgets: AvailableBudget[];
    categories: AvailableCategory[];
}

export interface CreateForecastItem {
    budgetId?: number;
    categoryId?: number;
    type: 'INCOME' | 'EXPENSE';
    expectedAmount: number;
}

export interface UpdateForecastItem {
    expectedAmount?: number;
    customName?: string | null;
    position?: number;
    type?: 'INCOME' | 'EXPENSE';
}

export interface PositionUpdate {
    id: number;
    position: number;
    type: 'INCOME' | 'EXPENSE';
}
