import api from '../../../lib/axios';

export interface PayPalImportResult {
    parsed: number;
    matched: number;
    imported: number;
    skipped: number;
}

export interface ParsedPayPalItem {
    id: string;
    date: string;
    merchant: string;
    amount: number;
    currency: string;
    reference: string;
    type: string;
}

export interface ParsePayPalCsvResult {
    items: ParsedPayPalItem[];
    count: number;
    totalParsed: number;
    alreadyLinked: number;
}

export interface BankPayPalTransaction {
    id: number;
    date: string;
    description: string;
    amount: number;
    hasSplits: boolean;
    splitCount: number;
}

export interface CreatePayPalLinkResult {
    created: number;
    parentId: number;
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

export async function parsePayPalCsv(
    accountId: number,
    file: File
): Promise<ParsePayPalCsvResult> {
    const formData = new FormData();
    formData.append('file', file);

    const response = await api.post(
        `/account/${accountId}/transactions/parse-paypal-csv`,
        formData,
        {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        }
    );

    return response.data;
}

export async function getUnmatchedPayPalTransactions(
    accountId: number
): Promise<{ transactions: BankPayPalTransaction[] }> {
    const response = await api.get(
        `/account/${accountId}/transactions/paypal-unmatched`
    );

    return response.data;
}

export async function createPayPalLinks(
    accountId: number,
    transactionId: number,
    items: Array<{ date: string; merchant: string; amount: number; reference?: string }>
): Promise<CreatePayPalLinkResult> {
    const response = await api.post(
        `/account/${accountId}/transactions/${transactionId}/paypal-link`,
        { items }
    );

    return response.data;
}
