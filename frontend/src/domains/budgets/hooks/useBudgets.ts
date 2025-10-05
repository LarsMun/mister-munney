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
    AssignCategories,
    CreateBudgetVersion,
    UpdateBudgetVersion
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
        } catch (err: any) {
            setError(err.response?.data?.message || err.response?.data?.error || 'Er is een fout opgetreden bij het laden van budgets');
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
        } catch (err: any) {
            console.error('Error loading available categories:', err);
        }
    };

    const createBudget = async (budget: CreateBudget) => {
        if (!accountId) return;

        const newBudget = await budgetsActions.createBudget(accountId, budget);
        await loadBudgets();
        await loadAvailableCategories();
        return newBudget;
    };

    const updateBudget = async (budgetId: number, budget: UpdateBudget) => {
        if (!accountId) return;

        const updatedBudget = await budgetsActions.updateBudget(accountId, budgetId, budget);
        await loadBudgets();
        return updatedBudget;
    };

    const deleteBudget = async (budgetId: number) => {
        if (!accountId) return;

        await budgetsActions.deleteBudget(accountId, budgetId);
        await loadBudgets();
        await loadAvailableCategories();
    };

    const createBudgetVersion = async (budgetId: number, version: CreateBudgetVersion) => {
        if (!accountId) return;

        const newVersion = await budgetsActions.createBudgetVersion(accountId, budgetId, version);
        await loadBudgets(); // Herlaad budgets om nieuwe versie te tonen
        return newVersion;
    };

    const updateBudgetVersion = async (budgetId: number, versionId: number, version: UpdateBudgetVersion) => {
        if (!accountId) return;

        const updatedVersion = await budgetsActions.updateBudgetVersion(accountId, budgetId, versionId, version);
        await loadBudgets(); // Herlaad budgets om wijzigingen te tonen
        return updatedVersion;
    };

    const deleteBudgetVersion = async (budgetId: number, versionId: number) => {
        if (!accountId) return;

        await budgetsActions.deleteBudgetVersion(accountId, budgetId, versionId);
        await loadBudgets(); // Herlaad budgets
    };

    const assignCategories = async (budgetId: number, data: AssignCategories) => {
        if (!accountId) return;

        await budgetsActions.assignCategories(accountId, budgetId, data);
        await loadBudgets();
        await loadAvailableCategories();
    };

    const removeCategory = async (budgetId: number, categoryId: number) => {
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
    }, [accountId]);

    return {
        budgets,
        availableCategories,
        isLoading,
        error,
        createBudget,
        updateBudget,
        deleteBudget,
        createBudgetVersion,
        updateBudgetVersion,
        deleteBudgetVersion,
        assignCategories,
        removeCategory,
        refresh
    };
}