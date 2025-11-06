import { apiRequest } from "../../../lib/api";

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
    const response = await apiRequest<PayPalImportResult>(
        '/transactions/import-paypal',
        {
            method: 'POST',
            body: JSON.stringify({ accountId, pastedText }),
        }
    );

    return response;
}
