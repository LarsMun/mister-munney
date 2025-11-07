import api from '../../../lib/axios';

export interface ParsedTransaction {
    date: string;
    description: string;
    amount: number;
    transaction_type: string;
    mutation_type: string;
    transaction_code: string;
    notes: string;
    counterparty_account: string | null;
    tag: string;
}

export interface ParseResult {
    transactions: ParsedTransaction[];
    total: number;
    parentAmount: number;
    valid: boolean;
    count: number;
}

export interface SplitTransaction {
    id: number;
    date: string;
    description: string;
    amount: string;
    transactionType: string;
    mutationType: string;
    transactionCode: string;
    notes: string;
    tag: string;
    category?: {
        id: number;
        name: string;
        color?: string | null;
    } | null;
}

/**
 * Parse credit card PDF text and extract transactions
 */
export async function parseCreditCardPdf(
    accountId: number,
    transactionId: number,
    pdfText: string
): Promise<ParseResult> {
    const response = await api.post(
        `/account/${accountId}/transaction/${transactionId}/parse-creditcard`,
        { pdfText }
    );

    return response.data;
}

/**
 * Create splits from parsed transactions
 */
export async function createSplits(
    accountId: number,
    transactionId: number,
    splits: ParsedTransaction[]
): Promise<{ parent: any; splits: SplitTransaction[]; message: string }> {
    const response = await api.post(
        `/account/${accountId}/transaction/${transactionId}/splits`,
        { splits }
    );

    return response.data;
}

/**
 * Get splits for a transaction
 */
export async function getSplits(
    accountId: number,
    transactionId: number
): Promise<SplitTransaction[]> {
    const response = await api.get(
        `/account/${accountId}/transaction/${transactionId}/splits`
    );

    return response.data;
}

/**
 * Delete all splits from a transaction
 */
export async function deleteSplits(
    accountId: number,
    transactionId: number
): Promise<void> {
    await api.delete(
        `/account/${accountId}/transaction/${transactionId}/splits`
    );
}

/**
 * Delete a single split transaction
 */
export async function deleteSplit(
    accountId: number,
    splitId: number
): Promise<void> {
    await api.delete(
        `/account/${accountId}/transaction/split/${splitId}`
    );
}

