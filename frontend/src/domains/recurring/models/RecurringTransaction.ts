export type RecurrenceFrequency = 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'yearly';

export type RecurringTransaction = {
    id: number;
    accountId: number;
    merchantPattern: string;
    displayName: string;
    predictedAmount: number;
    amountVariance: number;
    frequency: RecurrenceFrequency;
    frequencyLabel: string;
    confidenceScore: number;
    lastOccurrence: string | null;
    nextExpected: string | null;
    isActive: boolean;
    occurrenceCount: number;
    intervalConsistency: number;
    transactionType: 'debit' | 'credit';
    categoryId: number | null;
    categoryName: string | null;
    categoryColor: string | null;
    createdAt: string;
    updatedAt: string;
};

export type UpcomingTransaction = {
    id: number;
    displayName: string;
    predictedAmount: number;
    expectedDate: string;
    daysUntil: number;
    transactionType: 'debit' | 'credit';
    frequency: RecurrenceFrequency;
    categoryColor: string | null;
    categoryName: string | null;
};

export type RecurringSummary = {
    total: number;
    active: number;
    monthlyDebit: number;
    monthlyCredit: number;
};

export type GroupedRecurringTransactions = {
    weekly: RecurringTransaction[];
    biweekly: RecurringTransaction[];
    monthly: RecurringTransaction[];
    quarterly: RecurringTransaction[];
    yearly: RecurringTransaction[];
};

export type UpdateRecurringTransaction = {
    displayName?: string;
    isActive?: boolean;
    categoryId?: number | null;
};

export const FREQUENCY_LABELS: Record<RecurrenceFrequency, string> = {
    weekly: 'Wekelijks',
    biweekly: 'Tweewekelijks',
    monthly: 'Maandelijks',
    quarterly: 'Per kwartaal',
    yearly: 'Jaarlijks',
};

export const FREQUENCY_ORDER: RecurrenceFrequency[] = [
    'weekly',
    'biweekly',
    'monthly',
    'quarterly',
    'yearly',
];
