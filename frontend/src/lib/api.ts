const prefix = 'http://localhost:8686';
export async function fetchTransactions(
    accountId: number,
    filters: { startDate?: string; endDate?: string }
) {
    const params = new URLSearchParams(filters);
    const response = await fetch(
        `${prefix}/api/account/${accountId}/transactions?${params.toString()}`
    );
    return await response.json();
}

export async function fetchAvailableMonths(accountId: number): Promise<string[]> {
    const response = await fetch(`${prefix}/api/account/${accountId}/transactions/months`);
    return await response.json();
}