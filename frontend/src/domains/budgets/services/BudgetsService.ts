// frontend/src/domains/budgets/services/BudgetsService.ts

import api from "../../../lib/axios";
import type {
    Budget,
    CreateBudget,
    UpdateBudget,
    AvailableCategory,
    AssignCategories,
    CreateBudgetVersion,
    UpdateBudgetVersion,
    BudgetVersion
} from "../models/Budget";
import type { BudgetSummary, BudgetSummaryResponse } from "../models/BudgetSummary";
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
    monthYear: string
): Promise<CategoryBreakdown[]> {
    const res = await api.get(`/account/${accountId}/budget/${budgetId}/breakdown/${monthYear}`);
    return res.data;
}

// ===============================
// BUDGET VERSION CRUD
// ===============================

export function createBudgetVersion(
    accountId: number,
    budgetId: number,
    version: CreateBudgetVersion
): Promise<BudgetVersion> {
    return api.post(`/account/${accountId}/budget/${budgetId}/version/create`, version)
        .then(res => res.data);
}

export function updateBudgetVersion(
    accountId: number,
    budgetId: number,
    versionId: number,
    version: UpdateBudgetVersion
): Promise<BudgetVersion> {
    return api.put(`/account/${accountId}/budget/${budgetId}/version/${versionId}`, version)
        .then(res => res.data);
}

export function deleteBudgetVersion(
    accountId: number,
    budgetId: number,
    versionId: number
): Promise<void> {
    return api.delete(`/account/${accountId}/budget/${budgetId}/version/${versionId}`)
        .then(() => {});
}

// ===============================
// CATEGORY MANAGEMENT
// ===============================

export function getAvailableCategories(accountId: number): Promise<AvailableCategory[]> {
    return api.get(`/account/${accountId}/categories`)
        .then(res => {
            return res.data.map((category: any) => ({
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

/**
 * Get the currently active version for a budget based on today's date
 */
function getActiveVersion(budget: Budget): BudgetVersion | undefined {
    return budget.versions.find(v => v.isCurrent);
}

/**
 * Check if budget is currently active (has an active version right now)
 */
export function isCurrentlyActive(budget: Budget): boolean {
    return getActiveVersion(budget) !== undefined;
}

/**
 * Check if budget is future (all versions start in the future)
 */
export function isFuture(budget: Budget): boolean {
    const currentMonth = getCurrentMonth();
    // Budget is future if ALL versions start after current month
    return budget.versions.every(v => v.effectiveFromMonth > currentMonth);
}

/**
 * Check if budget is expired (all versions ended before current month)
 */
export function isExpired(budget: Budget): boolean {
    const currentMonth = getCurrentMonth();
    // Budget is expired if ALL versions have ended before current month
    return budget.versions.every(v =>
        v.effectiveUntilMonth !== null && v.effectiveUntilMonth < currentMonth
    );
}
