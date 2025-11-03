import api from '../../../lib/axios';
import type { Category } from '../models/Category';
import toast from "react-hot-toast";
import type { CategoryStatistics } from '../models/CategoryStatistics';

export async function fetchCategories(accountId: number): Promise<Category[]> {
    const response = await api.get(`/account/${accountId}/categories`);
    return response.data;
}

export async function createCategory(
    accountId: number,
    category: Partial<Category>,
    silent = false
): Promise<Category> {
    // Categories no longer have a transactionType - they can contain both CREDIT and DEBIT
    const response = await api.post(`/account/${accountId}/categories`, category);
    if (!silent) {
        toast.success('Categorie succesvol aangemaakt');
    }
    return response.data;
}

export async function assignCategoryToTransaction(
    accountId: number,
    transactionId: number,
    categoryId: number | null
): Promise<void> {
    const finalCategoryId = categoryId === null ? 0 : categoryId;

    await api.patch(`/account/${accountId}/transactions/${transactionId}/category`, {
        categoryId: finalCategoryId
    });
}

export async function removeCategoryFromTransaction(accountId: number, transactionId: number): Promise<void> {
    await api.patch(`/account/${accountId}/transactions/${transactionId}/category`, {
        categoryId: 0,
    });
}

// ✅ Bulk: meerdere transacties een categorie geven
export async function bulkAssignCategoryToTransactions(
    accountId: number,
    transactionIds: number[],
    categoryId: number
): Promise<void> {
    await api.post(`/account/${accountId}/transactions/bulk-assign-category`, {
        transactionIds,
        categoryId,
    });
}

// ✅ Bulk: categorieën verwijderen uit meerdere transacties
export async function bulkRemoveCategoryFromTransactions(
    accountId: number,
    transactionIds: number[]
): Promise<void> {
    await api.post(`/account/${accountId}/transactions/bulk-remove-category`, {
        transactionIds,
    });
}

export function getCategoryStatistics(
    accountId: number,
    months: string | number = 'all'
): Promise<CategoryStatistics> {
    return api.get(`/account/${accountId}/categories/statistics/by-category`, {
        params: { months }
    }).then(res => res.data);
}

export async function updateCategory(
    accountId: number,
    categoryId: number,
    updates: { name: string; color: string; icon: string | null }
): Promise<Category> {
    const response = await api.put(`/account/${accountId}/categories/${categoryId}`, updates);
    toast.success('Categorie succesvol bijgewerkt', { id: 'category-update-success' });
    return response.data;
}

export async function deleteCategory(accountId: number, categoryId: number): Promise<void> {
    await api.delete(`/account/${accountId}/categories/${categoryId}`);
    toast.success('Categorie succesvol verwijderd', { id: 'category-delete-success' });
}

export async function mergeCategories(
    accountId: number,
    sourceId: number,
    targetId: number
): Promise<{ success: boolean; transactionsMoved: number }> {
    const response = await api.post(`/account/${accountId}/categories/${sourceId}/merge/${targetId}`);

    const transactionsMoved = response.data.transactionsMoved || 0;
    toast.success(
        `Categorieën succesvol samengevoegd! ${transactionsMoved} transactie${transactionsMoved !== 1 ? 's' : ''} verplaatst.`,
        { id: 'category-merge-success', duration: 4000 }
    );

    return response.data;
}