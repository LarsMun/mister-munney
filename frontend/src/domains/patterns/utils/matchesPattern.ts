import type { PatternInput } from '../models/PatternInput';
import type { Transaction } from '../../transactions/models/Transaction';

export interface PatternMatchResult {
    total: number;
    matched: Transaction[];
    unassigned: number;
    assignedOther: number;
    assignedSame: number;
}

function matchString(value: string | null | undefined, pattern: string | undefined, type: string | undefined): boolean {
    if (!pattern || pattern === '') return true;
    const target = value?.toLowerCase() ?? '';
    const search = pattern.toLowerCase();

    switch (type) {
        case 'EXACT':
            return target === search;
        case 'REGEX':
            try {
                return new RegExp(pattern, 'i').test(target);
            } catch {
                return false;
            }
        case 'LIKE':
        default:
            return target.includes(search);
    }
}

export function matchesPattern(t: Transaction, pattern: PatternInput): boolean {
    const descMatch = matchString(t.description, pattern.description, pattern.matchTypeDescription);
    const notesMatch = matchString(t.notes, pattern.notes, pattern.matchTypeNotes);
    const tagMatch = matchString(t.tag, pattern.tag, 'LIKE');

    const typeMatch = !pattern.transactionType || t.transactionType === pattern.transactionType;

    const amountMatch =
        (!pattern.minAmount && !pattern.maxAmount) ||
        (pattern.minAmount ?? 0) <= t.amount && t.amount <= (pattern.maxAmount ?? Infinity);

    const dateMatch =
        (!pattern.startDate || t.date >= pattern.startDate) &&
        (!pattern.endDate || t.date <= pattern.endDate);

    return descMatch && notesMatch && tagMatch && typeMatch && amountMatch && dateMatch;
}

export function getPatternMatchResult(
    transactions: Transaction[],
    pattern: PatternInput
): PatternMatchResult {
    const matched = transactions.filter(t => matchesPattern(t, pattern));

    return {
        total: matched.length,
        unassigned: matched.filter(t => !t.category).length,
        assignedOther: matched.filter(t => t.category && t.category.id !== pattern.categoryId).length,
        assignedSame: matched.filter(t => t.category?.id === pattern.categoryId).length,
        matched: pattern.strict
            ? matched.filter(t => !t.category || t.category.id !== pattern.categoryId)
            : matched,
    };
}