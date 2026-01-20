import { useState, useEffect, useCallback } from 'react';
import { useAccount } from '../../../app/context/AccountContext';
import * as RecurringService from '../services/RecurringService';
import type { UpcomingTransaction } from '../models/RecurringTransaction';

export function useUpcomingTransactions(days: number = 30) {
    const { accountId } = useAccount();
    const [upcoming, setUpcoming] = useState<UpcomingTransaction[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const loadUpcoming = useCallback(async () => {
        if (!accountId) return;

        try {
            setIsLoading(true);
            setError(null);
            const data = await RecurringService.fetchUpcomingTransactions(accountId, days);
            setUpcoming(data);
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string; error?: string } } };
            setError(
                error.response?.data?.message ||
                error.response?.data?.error ||
                'Er is een fout opgetreden bij het laden van verwachte transacties'
            );
            console.error('Error loading upcoming transactions:', err);
        } finally {
            setIsLoading(false);
        }
    }, [accountId, days]);

    const refresh = useCallback(async () => {
        await loadUpcoming();
    }, [loadUpcoming]);

    useEffect(() => {
        loadUpcoming();
    }, [loadUpcoming]);

    // Calculate totals
    const totalDebit = upcoming
        .filter((t) => t.transactionType === 'debit')
        .reduce((sum, t) => sum + t.predictedAmount, 0);

    const totalCredit = upcoming
        .filter((t) => t.transactionType === 'credit')
        .reduce((sum, t) => sum + t.predictedAmount, 0);

    return {
        upcoming,
        isLoading,
        error,
        totalDebit,
        totalCredit,
        refresh,
    };
}
