import { API_URL } from '../../../lib/api';

const prefix = import.meta.env.VITE_API_URL || 'http://localhost:8787/api';

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
    categoryId: number | null;
    categoryName: string | null;
}

/**
 * Parse credit card PDF text and extract transactions
 */
export async function parseCreditCardPdf(
    accountId: number,
    transactionId: number,
    pdfText: string
): Promise<ParseResult> {
    const response = await fetch(
        `${prefix}/account/${accountId}/transaction/${transactionId}/parse-creditcard`,
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ pdfText }),
        }
    );

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Failed to parse credit card PDF');
    }

    return await response.json();
}

/**
 * Create splits from parsed transactions
 */
export async function createSplits(
    accountId: number,
    transactionId: number,
    splits: ParsedTransaction[]
): Promise<{ parent: any; splits: SplitTransaction[]; message: string }> {
    const response = await fetch(
        `${prefix}/account/${accountId}/transaction/${transactionId}/splits`,
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ splits }),
        }
    );

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Failed to create splits');
    }

    return await response.json();
}

/**
 * Get splits for a transaction
 */
export async function getSplits(
    accountId: number,
    transactionId: number
): Promise<SplitTransaction[]> {
    const response = await fetch(
        `${prefix}/account/${accountId}/transaction/${transactionId}/splits`
    );

    if (!response.ok) {
        throw new Error('Failed to fetch splits');
    }

    return await response.json();
}

/**
 * Delete all splits from a transaction
 */
export async function deleteSplits(
    accountId: number,
    transactionId: number
): Promise<void> {
    const response = await fetch(
        `${prefix}/account/${accountId}/transaction/${transactionId}/splits`,
        {
            method: 'DELETE',
        }
    );

    if (!response.ok) {
        throw new Error('Failed to delete splits');
    }
}

/**
 * Delete a single split transaction
 */
export async function deleteSplit(
    accountId: number,
    splitId: number
): Promise<void> {
    const response = await fetch(
        `${prefix}/account/${accountId}/transaction/split/${splitId}`,
        {
            method: 'DELETE',
        }
    );

    if (!response.ok) {
        throw new Error('Failed to delete split');
    }
}

/**
 * Extract text from PDF file using browser APIs
 */
export async function extractTextFromPdf(file: File): Promise<string> {
    // For now, we'll just read the file as text
    // In a real implementation, you might want to use a library like pdf.js
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = async (e) => {
            try {
                const text = e.target?.result as string;
                // For now, just return the text
                // TODO: Implement proper PDF text extraction using pdf.js or similar
                resolve(text);
            } catch (error) {
                reject(error);
            }
        };

        reader.onerror = () => {
            reject(new Error('Failed to read file'));
        };

        reader.readAsText(file);
    });
}
