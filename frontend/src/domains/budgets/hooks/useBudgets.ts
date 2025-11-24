// frontend/src/domains/budgets/hooks/useBudgets.ts

import { useState, useEffect } from 'react';
import { useAccount } from '../../../app/context/AccountContext';
import * as BudgetsService from '../services/BudgetsService';
import { budgetsActions } from '../services/BudgetsActions';
import type {
    Budget,
    CreateBudget,
    UpdateBudget,
    AvailableCategory,
    AssignCategories
} from '../models/Budget';

export function useBudgets() {
    const { accountId } = useAccount();
    const [budgets, setBudgets] = useState<Budget[]>([]);
    const [availableCategories, setAvailableCategories] = useState<AvailableCategory[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const loadBudgets = async () => {
        if (!accountId) return;

        try {
            setIsLoading(true);
            setError(null);
            const data = await BudgetsService.getBudgets(accountId);
            setBudgets(data);
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string; error?: string } } };
            setError(error.response?.data?.message || error.response?.data?.error || 'Er is een fout opgetreden bij het laden van budgets');
            console.error('Error loading budgets:', err);
        } finally {
            setIsLoading(false);
        }
    };

    const loadAvailableCategories = async () => {
        if (!accountId) return;

        try {
            const data = await BudgetsService.getAvailableCategories(accountId);
            setAvailableCategories(data);
        } catch (err: unknown) {
            console.error('Error loading available categories:', err);
        }
    };

    const createBudget = async (budget: CreateBudget): Promise<void> => {
        if (!accountId) return;

        await budgetsActions.createBudget(accountId, budget);
        await loadBudgets();
        await loadAvailableCategories();
    };

    const updateBudget = async (budgetId: number, budget: UpdateBudget): Promise<void> => {
        if (!accountId) return;

        await budgetsActions.updateBudget(accountId, budgetId, budget);
        await loadBudgets();
    };

    const deleteBudget = async (budgetId: number): Promise<void> => {
        if (!accountId) return;

        await budgetsActions.deleteBudget(accountId, budgetId);
        await loadBudgets();
        await loadAvailableCategories();
    };

    const assignCategories = async (budgetId: number, data: AssignCategories): Promise<void> => {
        if (!accountId) return;

        await budgetsActions.assignCategories(accountId, budgetId, data);
        await loadBudgets();
        await loadAvailableCategories();
    };

    const removeCategory = async (budgetId: number, categoryId: number): Promise<void> => {
        if (!accountId) return;

        await budgetsActions.removeCategory(accountId, budgetId, categoryId);
        await loadBudgets();
        await loadAvailableCategories();
    };

    const refresh = async () => {
        await Promise.all([loadBudgets(), loadAvailableCategories()]);
    };

    useEffect(() => {
        loadBudgets();
        loadAvailableCategories();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [accountId]);

    return {
        budgets,
        availableCategories,
        isLoading,
        error,
        createBudget,
        updateBudget,
        deleteBudget,
        assignCategories,
        removeCategory,
        refresh
    };
}
