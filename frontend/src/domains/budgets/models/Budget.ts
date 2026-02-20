// frontend/src/domains/budgets/models/Budget.ts

export type BudgetType = 'EXPENSE' | 'INCOME' | 'PROJECT';

export interface Budget {
    id: number;
    name: string;
    accountId: number;
    budgetType: BudgetType;
    icon?: string | null;
    isActive: boolean;
    createdAt: string;
    updatedAt: string;
    categories: Category[];
}

export interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
    budgetId: number | null;
}

export interface CreateBudget {
    name: string;
    accountId: number;
    budgetType: BudgetType;
    icon?: string | null;
    categoryIds?: number[];
}

export interface UpdateBudget {
    name?: string;
    budgetType?: BudgetType;
    icon?: string | null;
    isActive?: boolean;
}

export interface AssignCategories {
    categoryIds: number[];
}

export interface AvailableCategory {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
    budgetId: number | null;
    isAssigned: boolean;
}
