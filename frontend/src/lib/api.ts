const prefix = import.meta.env.VITE_API_URL || 'http://localhost:8787/api';

export async function fetchTransactions(
    accountId: number,
    filters: { startDate?: string; endDate?: string }
) {
    const params = new URLSearchParams(filters);
    const response = await fetch(
        `${prefix}/account/${accountId}/transactions?${params.toString()}`
    );
    return await response.json();
}

export async function fetchAvailableMonths(accountId: number): Promise<string[]> {
    const response = await fetch(`${prefix}/account/${accountId}/transactions/months`);
    return await response.json();
}

export async function importTransactions(accountId: number, file: File): Promise<{ imported: number; duplicates: number }> {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('accountId', accountId.toString());

    const response = await fetch(`${prefix}/transactions/import`, {
        method: 'POST',
        body: formData,
    });

    if (!response.ok) {
        throw new Error('Failed to import transactions');
    }

    return await response.json();
}
