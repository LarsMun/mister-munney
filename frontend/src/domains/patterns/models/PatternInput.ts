// src/domains/patterns/models/PatternInput.ts

export type matchTypeNotes = "LIKE" | "EXACT";
export type TransactionType = "debit" | "credit" | "both";
export type PatternType = "category" | "savings";

export interface PatternInput {
    accountId: number;
    patternType: PatternType;
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
    savingsAccountId?: number;
    strict?: boolean;
}