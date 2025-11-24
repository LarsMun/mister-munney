// frontend/src/domains/budgets/services/BudgetsActions.ts

import toast from 'react-hot-toast';
import * as BudgetsService from './BudgetsService';
import type {
    CreateBudget,
    UpdateBudget,
    AssignCategories
} from '../models/Budget';

interface ApiError {
    response?: {
        data?: {
            message?: string;
            error?: string;
        };
    };
}

function getErrorMessage(error: unknown, defaultMessage: string): string {
    const err = error as ApiError;
    return err.response?.data?.message || err.response?.data?.error || defaultMessage;
}

export const budgetsActions = {
    async createBudget(accountId: number, budget: CreateBudget) {
        try {
            const result = await BudgetsService.createBudget(accountId, budget);
            toast.success('Budget succesvol aangemaakt!');
            return result;
        } catch (error: unknown) {
            toast.error(getErrorMessage(error, 'Er is een fout opgetreden bij het aanmaken van het budget'));
            throw error;
        }
    },

    async updateBudget(accountId: number, budgetId: number, budget: UpdateBudget) {
        try {
            const result = await BudgetsService.updateBudget(accountId, budgetId, budget);
            toast.success('Budget succesvol bijgewerkt!');
            return result;
        } catch (error: unknown) {
            toast.error(getErrorMessage(error, 'Er is een fout opgetreden bij het bijwerken van het budget'));
            throw error;
        }
    },

    async deleteBudget(accountId: number, budgetId: number) {
        try {
            await BudgetsService.deleteBudget(accountId, budgetId);
            toast.success('Budget succesvol verwijderd!');
        } catch (error: unknown) {
            toast.error(getErrorMessage(error, 'Er is een fout opgetreden bij het verwijderen van het budget'));
            throw error;
        }
    },

    async assignCategories(accountId: number, budgetId: number, data: AssignCategories) {
        try {
            await BudgetsService.assignCategories(accountId, budgetId, data);
            toast.success('Categorieën succesvol toegewezen!');
        } catch (error: unknown) {
            toast.error(getErrorMessage(error, 'Er is een fout opgetreden bij het toewijzen van categorieën'));
            throw error;
        }
    },

    async removeCategory(accountId: number, budgetId: number, categoryId: number) {
        try {
            await BudgetsService.removeCategory(accountId, budgetId, categoryId);
            toast.success('Categorie succesvol verwijderd uit budget!');
        } catch (error: unknown) {
            toast.error(getErrorMessage(error, 'Er is een fout opgetreden bij het verwijderen van de categorie'));
            throw error;
        }
    }
};