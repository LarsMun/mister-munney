// PatternService.ts

import {PatternInput} from "../models/PatternInput.ts";
import {Transaction} from "../../../types.tsx";
import api from "../../../lib/axios.ts";
import {PatternDTO} from "../models/PatternDTO.ts";

export async function fetchPatternMatches(accountId: number, pattern: PatternInput): Promise<{ total: number, data: Transaction[] }> {
    const sanitized = sanitizePattern(pattern);
    console.log(sanitized);
    const response = await api.post(
        `/account/${accountId}/patterns/match`,
        sanitized
    );
    return response.data;
}

export function sanitizePattern(p: PatternInput): Record<string, any> {
    const clean: Record<string, any> = {
        accountId: p.accountId,
    };

    if (p.description?.trim()) {
        clean.description = p.description?.trim();
        // Alleen toevoegen als het een geldige waarde is
        if (p.matchTypeDescription === "LIKE" || p.matchTypeDescription === "EXACT") {
            clean.matchTypeDescription = p.matchTypeDescription;
        }
    }

    if (p.notes?.trim()) {
        clean.notes = p.notes?.trim();
        // Alleen toevoegen als het een geldige waarde is
        if (p.matchTypeNotes === "LIKE" || p.matchTypeNotes === "EXACT") {
            clean.matchTypeNotes = p.matchTypeNotes;
        }
    }

    if (p.tag?.trim()) {
        clean.tag = p.tag?.trim();
    }

    // Only add transactionType if it's debit or credit (not 'both')
    if (p.transactionType && p.transactionType !== "both") {
        clean.transactionType = p.transactionType;
    }
    if (typeof p.minAmount === "number") clean.minAmount = p.minAmount;
    if (typeof p.maxAmount === "number") clean.maxAmount = p.maxAmount;
    if (p.startDate) clean.startDate = p.startDate;
    if (p.endDate) clean.endDate = p.endDate;
    if (p.categoryId) clean.categoryId = p.categoryId;
    if (p.savingsAccountId) clean.savingsAccountId = p.savingsAccountId;

    return clean;
}

export async function getPatternsForAccount(accountId: number): Promise<PatternDTO[]> {
    const response = await api.get(`/account/${accountId}/patterns`);
    return response.data;
}

export async function createPattern(accountId: number, payload: any) {
    const sanitized = sanitizePattern(payload);
    const response = await api.post(`/account/${accountId}/patterns`, sanitized);
    return response.data;
}

export async function updatePattern(accountId: number, patternId: number, payload: any): Promise<PatternDTO> {
    const sanitized = sanitizePattern(payload);
    const response = await api.patch(`/account/${accountId}/patterns/${patternId}`, sanitized);
    return response.data;
}

export function deletePattern(accountId: number, patternId: number) {
    return api.delete(`/account/${accountId}/patterns/${patternId}`);
}

export async function deletePatternsWithoutCategory(accountId: number): Promise<{ deletedCount: number; message: string }> {
    const response = await api.delete(`/account/${accountId}/patterns/without-category`);
    return response.data;
}

export interface PatternSuggestion {
    patternString: string;
    suggestedCategoryName: string;
    existingCategoryId: number | null;
    matchCount: number;
    exampleTransactions: Array<{
        id: number;
        description: string;
        amount: number;
        date: string;
    }>;
    confidence: number;
    reasoning: string;
}

export async function discoverPatterns(accountId: number): Promise<{
    patterns: PatternSuggestion[];
    totalUncategorized: number;
}> {
    const response = await api.post(`/account/${accountId}/patterns/discover`);
    return response.data;
}

export async function acceptPatternSuggestion(
    accountId: number,
    suggestion: {
        patternString: string;
        categoryName: string;
        categoryId?: number | null;
        categoryColor?: string | null;
    }
): Promise<{
    message: string;
    pattern: PatternDTO;
    appliedToTransactions: number;
}> {
    const response = await api.post(`/account/${accountId}/patterns/discover/accept`, suggestion);
    return response.data;
}
