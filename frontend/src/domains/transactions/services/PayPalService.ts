import api from '../../../lib/axios';

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
    const response = await api.post('/transactions/import-paypal', {
        accountId,
        pastedText,
    });

    return response.data;
}
