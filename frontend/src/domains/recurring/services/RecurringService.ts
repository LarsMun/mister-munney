import api from '../../../lib/axios';
import type {
    RecurringTransaction,
    UpcomingTransaction,
    RecurringSummary,
    GroupedRecurringTransactions,
    UpdateRecurringTransaction,
    RecurrenceFrequency,
} from '../models/RecurringTransaction';
import toast from 'react-hot-toast';

export async function fetchRecurringTransactions(
    accountId: number,
    frequency?: RecurrenceFrequency,
    active?: boolean
): Promise<RecurringTransaction[]> {
    const params: Record<string, string | boolean> = {};
    if (frequency) params.frequency = frequency;
    if (active !== undefined) params.active = active;

    const response = await api.get(`/account/${accountId}/recurring-transactions`, { params });
    return response.data;
}

export async function fetchGroupedRecurringTransactions(
    accountId: number
): Promise<GroupedRecurringTransactions> {
    const response = await api.get(`/account/${accountId}/recurring-transactions/grouped`);
    return response.data;
}

export async function fetchRecurringSummary(
    accountId: number
): Promise<RecurringSummary> {
    const response = await api.get(`/account/${accountId}/recurring-transactions/summary`);
    return response.data;
}

export async function fetchUpcomingTransactions(
    accountId: number,
    days: number = 30
): Promise<UpcomingTransaction[]> {
    const response = await api.get(`/account/${accountId}/recurring-transactions/upcoming`, {
        params: { days },
    });
    return response.data;
}

export async function fetchRecurringTransaction(
    accountId: number,
    id: number
): Promise<RecurringTransaction> {
    const response = await api.get(`/account/${accountId}/recurring-transactions/${id}`);
    return response.data;
}

export async function updateRecurringTransaction(
    accountId: number,
    id: number,
    data: UpdateRecurringTransaction
): Promise<RecurringTransaction> {
    const response = await api.patch(`/account/${accountId}/recurring-transactions/${id}`, data);
    toast.success('Terugkerende transactie bijgewerkt');
    return response.data;
}

export async function deactivateRecurringTransaction(
    accountId: number,
    id: number
): Promise<void> {
    await api.delete(`/account/${accountId}/recurring-transactions/${id}`);
    toast.success('Terugkerende transactie gedeactiveerd');
}

export async function detectRecurringTransactions(
    accountId: number,
    force: boolean = false
): Promise<{ detected: number; items: RecurringTransaction[] }> {
    const response = await api.post(
        `/account/${accountId}/recurring-transactions/detect`,
        null,
        { params: { force } }
    );

    const { detected } = response.data;
    if (detected > 0) {
        toast.success(`${detected} terugkerende transactie(s) gedetecteerd`);
    } else {
        toast.success('Geen nieuwe terugkerende transacties gevonden');
    }

    return response.data;
}

export type LinkedTransaction = {
    id: number;
    date: string;
    description: string;
    amount: number;
    categoryId: number | null;
    categoryName: string | null;
    categoryColor: string | null;
};

export async function fetchLinkedTransactions(
    accountId: number,
    recurringId: number,
    limit: number = 20
): Promise<LinkedTransaction[]> {
    const response = await api.get(
        `/account/${accountId}/recurring-transactions/${recurringId}/transactions`,
        { params: { limit } }
    );
    return response.data;
}
