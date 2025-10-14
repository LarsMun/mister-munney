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
