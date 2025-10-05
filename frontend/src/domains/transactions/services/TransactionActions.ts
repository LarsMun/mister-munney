// src/domains/transactions/services/TransactionActions.ts

import { Category } from "../../categories/models/Category";
import toast from "react-hot-toast";
import {
    assignCategoryToTransaction,
    bulkAssignCategoryToTransactions, bulkRemoveCategoryFromTransactions, removeCategoryFromTransaction
} from "../../categories/services/CategoryService.ts";

export async function updateSingleTransactionCategory(
    accountId: number,
    transactionId: number,
    category: Category,
    refresh: () => Promise<void>
) {
    try {
        await assignCategoryToTransaction(accountId, transactionId, category.id);
        await refresh();
        toast.success(`Categorie bijgewerkt!`);
    } catch (error) {
        toast.error('Kon categorie niet bijwerken: {error}');
    }
}

export async function clearSingleTransactionCategory(accountId: number, transactionId: number, refresh: () => void) {
    await removeCategoryFromTransaction(accountId, transactionId);
    await refresh();
    toast.success(`Categorie verwijderd!`);
}


export async function assignCategoryToMultipleTransactions(
    accountId: number,
    transactionIds: number[],
    category: Category | null,
    refresh: () => Promise<void>,
    clearSelection: () => void
) {
    try {
        await bulkAssignCategoryToTransactions(accountId, transactionIds, category.id);
        await refresh();
        clearSelection();
        toast.success(`${transactionIds.length} transacties bijgewerkt!`);
    } catch (error) {
        toast.error('Kon transacties niet bijwerken.');
    }
}

export async function removeCategoryFromMultipleTransactions(
    accountId: number,
    transactionIds: number[],
    refresh: () => Promise<void>,
    clearSelection: () => void
) {
    try {
        await bulkRemoveCategoryFromTransactions(accountId, transactionIds);
        await refresh();
        clearSelection();
        toast.success(`${transactionIds.length} transacties verwijderd uit categorie!`);
    } catch (error) {
        toast.error('Kon transacties niet verwijderen.');
    }
}