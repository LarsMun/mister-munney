export interface PatternDTO {
    id: number;
    accountId: number;
    description?: string;
    matchTypeDescription?: "LIKE" | "EXACT";
    notes?: string;
    matchTypeNotes?: "LIKE" | "EXACT";
    tag?: string;
    transactionType?: "debit" | "credit";
    minAmount?: number;
    maxAmount?: number;
    startDate?: string;
    endDate?: string;
    category?: {
        id: number;
        name: string;
        color?: string;
    };
    strict?: boolean;
}