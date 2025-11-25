// src/domains/patterns/models/PatternInput.ts

export type matchTypeNotes = "LIKE" | "EXACT";
export type TransactionType = "debit" | "credit" | "both";

export interface PatternInput {
    accountId: number;
    description?: string;
    matchTypeDescription?: "LIKE" | "EXACT";
    notes?: string;
    matchTypeNotes?: "LIKE" | "EXACT";
    tag?: string;
    transactionType?: "debit" | "credit" | "both";
    minAmount?: number;
    maxAmount?: number;
    startDate?: string;
    endDate?: string;
    categoryId?: number;
    strict?: boolean;
}