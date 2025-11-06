const prefix = import.meta.env.VITE_API_URL || 'http://localhost:8787/api';

export interface PayPalImportResult {
    parsed: number;
    matched: number;
    imported: number;
    skipped: number;
}

export async function importPayPalTransactions(
    accountId: number,
    pastedText: string
): Promise<PayPalImportResult> {
    const response = await fetch(`${prefix}/transactions/import-paypal`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ accountId, pastedText }),
    });

    if (!response.ok) {
        throw new Error('Failed to import PayPal transactions');
    }

    return await response.json();
}
