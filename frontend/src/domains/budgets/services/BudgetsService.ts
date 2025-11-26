// frontend/src/domains/budgets/services/BudgetsService.ts

import api from "../../../lib/axios";
import type {
    Budget,
    CreateBudget,
    UpdateBudget,
    AvailableCategory,
    AssignCategories
} from "../models/Budget";
import type { BudgetSummaryResponse } from "../models/BudgetSummary";
import type { CategoryBreakdown } from "../models/CategoryBreakdown";

// ===============================
// BUDGET CRUD
// ===============================

export async function getBudgets(accountId: number): Promise<Budget[]> {
    const res = await api.get(`/account/${accountId}/budget`);
    return res.data;
}

export function getBudget(accountId: number, budgetId: number): Promise<Budget> {
    return api.get(`/account/${accountId}/budget/${budgetId}`)
        .then(res => res.data);
}

export function getBudgetsForMonth(accountId: number, monthYear: string): Promise<Budget[]> {
    return api.get(`/account/${accountId}/budget/month/${monthYear}`)
        .then(res => res.data);
}

export function createBudget(accountId: number, budget: CreateBudget): Promise<Budget> {
    return api.post(`/account/${accountId}/budget/create`, budget)
        .then(res => res.data);
}

export function updateBudget(accountId: number, budgetId: number, budget: UpdateBudget): Promise<Budget> {
    return api.put(`/account/${accountId}/budget/${budgetId}`, budget)
        .then(res => res.data);
}

export function deleteBudget(accountId: number, budgetId: number): Promise<void> {
    return api.delete(`/account/${accountId}/budget/${budgetId}`)
        .then(() => {});
}

// ===============================
// BUDGET SUMMARIES
// ===============================

export async function getBudgetSummaries(accountId: number, monthYear: string): Promise<BudgetSummaryResponse> {
    const res = await api.get(`/account/${accountId}/budget/summary/${monthYear}`);
    return res.data;
}

export async function getCategoryBreakdown(
    accountId: number,
    budgetId: number,
    startDate: string,
    endDate: string
): Promise<CategoryBreakdown[]> {
    const res = await api.get(`/account/${accountId}/budget/${budgetId}/breakdown-range`, {
        params: {
            startDate,
            endDate
        }
    });
    return res.data;
}

// ===============================
// CATEGORY MANAGEMENT
// ===============================

interface CategoryResponse {
    id: number;
    name: string;
    color?: string;
    icon?: string;
    budgetId?: number;
}

export function getAvailableCategories(accountId: number): Promise<AvailableCategory[]> {
    return api.get(`/account/${accountId}/categories`)
        .then(res => {
            return res.data.map((category: CategoryResponse) => ({
                id: category.id,
                name: category.name,
                color: category.color,
                icon: category.icon,
                isAssigned: !!category.budgetId
            }));
        });
}

export function assignCategories(
    accountId: number,
    budgetId: number,
    data: AssignCategories
): Promise<Budget> {
    return api.put(`/account/${accountId}/budget/${budgetId}/categories`, data)
        .then(res => res.data);
}

export function removeCategory(
    accountId: number,
    budgetId: number,
    categoryId: number
): Promise<Budget> {
    return api.delete(`/account/${accountId}/budget/${budgetId}/categories/${categoryId}`)
        .then(res => res.data);
}

// ===============================
// UTILITY FUNCTIONS
// ===============================

export function formatDateToMonth(date: Date): string {
    return date.toISOString().substring(0, 7);
}

export function getCurrentMonth(): string {
    return formatDateToMonth(new Date());
}

export function getNextMonth(): string {
    const next = new Date();
    next.setMonth(next.getMonth() + 1);
    return formatDateToMonth(next);
}

// ===============================
// BUDGET HISTORY
// ===============================

export interface BudgetHistory {
    budget: {
        id: number;
        name: string;
        budgetType: string;
        categoryIds: number[];
    };
    history: Array<{
        month: string;
        total: number;
        transactionCount: number;
    }>;
    totalAmount: number;
    averagePerMonth: number;
    monthCount: number;
}

export async function fetchBudgetHistory(
    accountId: number,
    budgetId: number,
    months?: number
): Promise<BudgetHistory> {
    const params = months ? { months } : {};
    const response = await api.get(`/account/${accountId}/budget/${budgetId}/history`, { params });
    return response.data;
}
