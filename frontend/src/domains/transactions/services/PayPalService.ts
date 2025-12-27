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
    const response = await api.post(`/account/${accountId}/transactions/import-paypal`, {
        pastedText,
    });

    return response.data;
}

export async function importPayPalCsv(
    accountId: number,
    file: File
): Promise<PayPalImportResult> {
    const formData = new FormData();
    formData.append('file', file);

    const response = await api.post(
        `/account/${accountId}/transactions/import-paypal-csv`,
        formData,
        {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        }
    );

    return response.data;
}
