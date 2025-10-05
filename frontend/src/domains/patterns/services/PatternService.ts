// PatternService.ts

import {PatternInput} from "../models/PatternInput.ts";
import {Transaction} from "../../../types.tsx";
import {api} from "../../../lib/axios.ts";
import {PatternDTO} from "../models/PatternDTO.ts";

export async function fetchPatternMatches(accountId: number, pattern: PatternInput): Promise<{ total: number, data: Transaction[] }> {
    pattern = sanitizePattern(pattern);
    console.log(pattern);
    const response = await api.post(
        `/account/${accountId}/patterns/match`,
        pattern
    );
    return response.data;
}

export function sanitizePattern(p: PatternInput): Record<string, any> {
    const clean: Record<string, any> = {
        accountId: p.accountId,
        //patternType: p.patternType,
        //strict: p.strict ?? false,
    };

    if (p.description?.trim()) {
        clean.description = p.description?.trim();
        clean.matchTypeDescription = p.matchTypeDescription;
    }

    if (p.notes?.trim()) {
        clean.notes = p.notes?.trim();
        clean.matchTypeNotes = p.matchTypeNotes;
    }

    if (p.tag?.trim()) {
        clean.tag = p.tag?.trim();
    }

    if (p.transactionType) clean.transactionType = p.transactionType;
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
    const response = await api.post(`/account/${accountId}/patterns`, payload);
    return response.data;
}

export function deletePattern(accountId: number, patternId: number) {
    return api.delete(`/account/${accountId}/patterns/${patternId}`);
}