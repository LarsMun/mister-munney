import { useState, useEffect, useCallback } from 'react';
import { useAccount } from '../../../app/context/AccountContext';
import * as RecurringService from '../services/RecurringService';
import type {
    RecurringTransaction,
    GroupedRecurringTransactions,
    RecurringSummary,
    UpdateRecurringTransaction,
} from '../models/RecurringTransaction';

export function useRecurringTransactions() {
    const { accountId } = useAccount();
    const [transactions, setTransactions] = useState<RecurringTransaction[]>([]);
    const [grouped, setGrouped] = useState<GroupedRecurringTransactions | null>(null);
    const [summary, setSummary] = useState<RecurringSummary | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [isDetecting, setIsDetecting] = useState(false);

    const loadTransactions = useCallback(async () => {
        if (!accountId) return;

        try {
            setIsLoading(true);
            setError(null);
            const data = await RecurringService.fetchRecurringTransactions(accountId);
            setTransactions(data);
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string; error?: string } } };
            setError(
                error.response?.data?.message ||
                error.response?.data?.error ||
                'Er is een fout opgetreden bij het laden van terugkerende transacties'
            );
            console.error('Error loading recurring transactions:', err);
        } finally {
            setIsLoading(false);
        }
    }, [accountId]);

    const loadGrouped = useCallback(async () => {
        if (!accountId) return;

        try {
            const data = await RecurringService.fetchGroupedRecurringTransactions(accountId);
            setGrouped(data);
        } catch (err) {
            console.error('Error loading grouped recurring transactions:', err);
        }
    }, [accountId]);

    const loadSummary = useCallback(async () => {
        if (!accountId) return;

        try {
            const data = await RecurringService.fetchRecurringSummary(accountId);
            setSummary(data);
        } catch (err) {
            console.error('Error loading recurring summary:', err);
        }
    }, [accountId]);

    const updateTransaction = async (
        id: number,
        data: UpdateRecurringTransaction
    ): Promise<RecurringTransaction | null> => {
        if (!accountId) return null;

        try {
            const updated = await RecurringService.updateRecurringTransaction(accountId, id, data);
            await refresh();
            return updated;
        } catch (err) {
            console.error('Error updating recurring transaction:', err);
            return null;
        }
    };

    const deactivateTransaction = async (id: number): Promise<boolean> => {
        if (!accountId) return false;

        try {
            await RecurringService.deactivateRecurringTransaction(accountId, id);
            await refresh();
            return true;
        } catch (err) {
            console.error('Error deactivating recurring transaction:', err);
            return false;
        }
    };

    const detectTransactions = async (force: boolean = false): Promise<number> => {
        if (!accountId) return 0;

        try {
            setIsDetecting(true);
            const result = await RecurringService.detectRecurringTransactions(accountId, force);
            await refresh();
            return result.detected;
        } catch (err) {
            console.error('Error detecting recurring transactions:', err);
            return 0;
        } finally {
            setIsDetecting(false);
        }
    };

    const refresh = useCallback(async () => {
        await Promise.all([loadTransactions(), loadGrouped(), loadSummary()]);
    }, [loadTransactions, loadGrouped, loadSummary]);

    useEffect(() => {
        loadTransactions();
        loadGrouped();
        loadSummary();
    }, [loadTransactions, loadGrouped, loadSummary]);

    return {
        transactions,
        grouped,
        summary,
        isLoading,
        error,
        isDetecting,
        updateTransaction,
        deactivateTransaction,
        detectTransactions,
        refresh,
    };
}
