import api from './axios';

export const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8787';

// Base URL without /api for static files (uploads, etc.)
// Remove /api suffix from API_URL to get the base URL
export const BASE_URL = API_URL.replace(/\/api\/?$/, '');

export async function fetchTransactions(
    accountId: number,
    filters: { startDate?: string; endDate?: string }
) {
    const params = new URLSearchParams(filters);
    const response = await api.get(`/account/${accountId}/transactions?${params.toString()}`);
    return response.data;
}

export async function fetchAvailableMonths(accountId: number): Promise<string[]> {
    const response = await api.get(`/account/${accountId}/transactions/months`);
    return response.data;
}

export async function importTransactions(accountId: number, file: File): Promise<{ imported: number; duplicates: number }> {
    const formData = new FormData();
    formData.append('file', file);

    const response = await api.post(`/account/${accountId}/transactions/import`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    });

    return response.data;
}

export async function fetchIcons(): Promise<string[]> {
    const response = await api.get('/icons');
    return response.data;
}
