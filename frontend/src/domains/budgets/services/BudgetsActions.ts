// frontend/src/domains/budgets/services/BudgetsActions.ts

import toast from 'react-hot-toast';
import * as BudgetsService from './BudgetsService';
import type {
    CreateBudget,
    UpdateBudget,
    AssignCategories,
    CreateBudgetVersion,
    UpdateBudgetVersion
} from '../models/Budget';

export const budgetsActions = {
    async createBudget(accountId: number, budget: CreateBudget) {
        try {
            const result = await BudgetsService.createBudget(accountId, budget);
            toast.success('Budget succesvol aangemaakt!');
            return result;
        } catch (error: any) {
            const message = error.response?.data?.message || error.response?.data?.error || 'Er is een fout opgetreden bij het aanmaken van het budget';
            toast.error(message);
            throw error;
        }
    },

    async updateBudget(accountId: number, budgetId: number, budget: UpdateBudget) {
        try {
            const result = await BudgetsService.updateBudget(accountId, budgetId, budget);
            toast.success('Budget succesvol bijgewerkt!');
            return result;
        } catch (error: any) {
            const message = error.response?.data?.message || error.response?.data?.error || 'Er is een fout opgetreden bij het bijwerken van het budget';
            toast.error(message);
            throw error;
        }
    },

    async deleteBudget(accountId: number, budgetId: number) {
        try {
            await BudgetsService.deleteBudget(accountId, budgetId);
            toast.success('Budget succesvol verwijderd!');
        } catch (error: any) {
            const message = error.response?.data?.message || error.response?.data?.error || 'Er is een fout opgetreden bij het verwijderen van het budget';
            toast.error(message);
            throw error;
        }
    },

    async createBudgetVersion(accountId: number, budgetId: number, version: CreateBudgetVersion) {
        try {
            const result = await BudgetsService.createBudgetVersion(accountId, budgetId, version);
            toast.success('Budget versie succesvol toegevoegd!');
            return result;
        } catch (error: any) {
            const message = error.response?.data?.message || error.response?.data?.error || 'Er is een fout opgetreden bij het toevoegen van de budget versie';
            toast.error(message);
            throw error;
        }
    },

    async updateBudgetVersion(accountId: number, budgetId: number, versionId: number, version: UpdateBudgetVersion) {
        try {
            const result = await BudgetsService.updateBudgetVersion(accountId, budgetId, versionId, version);
            toast.success('Budget versie succesvol bijgewerkt!');
            return result;
        } catch (error: any) {
            const message = error.response?.data?.message || error.response?.data?.error || 'Er is een fout opgetreden bij het bijwerken van de budget versie';
            toast.error(message);
            throw error;
        }
    },

    async deleteBudgetVersion(accountId: number, budgetId: number, versionId: number) {
        try {
            await BudgetsService.deleteBudgetVersion(accountId, budgetId, versionId);
            toast.success('Budget versie succesvol verwijderd!');
        } catch (error: any) {
            const message = error.response?.data?.message || error.response?.data?.error || 'Er is een fout opgetreden bij het verwijderen van de budget versie';
            toast.error(message);
            throw error;
        }
    },

    async assignCategories(accountId: number, budgetId: number, data: AssignCategories) {
        try {
            await BudgetsService.assignCategories(accountId, budgetId, data);
            toast.success('Categorieën succesvol toegewezen!');
        } catch (error: any) {
            const message = error.response?.data?.message || error.response?.data?.error || 'Er is een fout opgetreden bij het toewijzen van categorieën';
            toast.error(message);
            throw error;
        }
    },

    async removeCategory(accountId: number, budgetId: number, categoryId: number) {
        try {
            await BudgetsService.removeCategory(accountId, budgetId, categoryId);
            toast.success('Categorie succesvol verwijderd uit budget!');
        } catch (error: any) {
            const message = error.response?.data?.message || error.response?.data?.error || 'Er is een fout opgetreden bij het verwijderen van de categorie';
            toast.error(message);
            throw error;
        }
    }
};