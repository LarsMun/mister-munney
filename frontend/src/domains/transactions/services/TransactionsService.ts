import api from "../../../lib/axios";
import type { Transaction } from "../models/Transaction";
import type { SummaryType } from "../models/SummaryType";
import type { TreeMapDataType } from "../models/TreeMapDataType";
import type { MonthlyStatistics } from "../models/MonthlyStatistics";

export interface ImportTransactionsResponse {
    message: string;
    imported: number;
    skipped: number;
    errors?: string[];
}

export function getAvailableMonths(accountId: number): Promise<string[]> {
    return api.get(`/account/${accountId}/transactions/months`)
        .then(res => res.data);
}

export function getTransactions(
    accountId: number,
    startDate: string,
    endDate: string
): Promise<{ data: Transaction[]; summary: SummaryType; treeMapData: TreeMapDataType }> {
    return api.get(`/account/${accountId}/transactions`, { params: { startDate, endDate } })
        .then(res => ({
            data: res.data.data,
            summary: res.data.summary,
            treeMapData: res.data.treeMapData,
        }));
}

export function getAllTransactions(accountId: number): Promise<Transaction[]> {
    return api.get(`/account/${accountId}/transactions`)
        .then(res => res.data.data);
}

export function importTransactions(_accountId: number, file: File): Promise<ImportTransactionsResponse> {
    const formData = new FormData();
    formData.append("file", file);

    // Fixed URL: was /transactions/import_transactions, now /transactions/import
    return api.post(`/transactions/import`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    }).then(res => res.data);
}

export function createTemporaryTransaction(
    accountId: number,
    data: { date: string; description: string; amount: number; transactionType: string; categoryId?: number }
): Promise<Transaction> {
    return api.post(`/account/${accountId}/transactions/temporary`, data)
        .then(res => res.data);
}

export function deleteTemporaryTransaction(
    accountId: number,
    transactionId: number
): Promise<void> {
    return api.delete(`/account/${accountId}/transactions/${transactionId}/temporary`);
}

export function getMonthlyStatistics(
    accountId: number,
    months: string | number = 'all'
): Promise<MonthlyStatistics> {
    return api.get(`/account/${accountId}/transactions/statistics/monthly-median`, {
        params: { months }
    }).then(res => res.data);
}