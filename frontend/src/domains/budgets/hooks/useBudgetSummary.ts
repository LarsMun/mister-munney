// frontend/src/domains/budgets/hooks/useBudgetSummary.ts

import { useState, useEffect, useCallback } from 'react';
import { getBudgetSummaries } from '../services/BudgetsService';
import type { BudgetSummary } from '../models/BudgetSummary';

export function useBudgetSummary(accountId: number | null, monthYear: string) {
    const [summaries, setSummaries] = useState<BudgetSummary[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchSummaries = useCallback(async () => {
        if (!accountId) {
            setSummaries([]);
            return;
        }

        setIsLoading(true);
        setError(null);

        try {
            const data = await getBudgetSummaries(accountId, monthYear);
            setSummaries(data);
        } catch (err: any) {
            console.error('Error fetching budget summaries:', err);
            setError(err.response?.data?.message || 'Failed to load budget summaries');
            setSummaries([]);
        } finally {
            setIsLoading(false);
        }
    }, [accountId, monthYear]);

    useEffect(() => {
        fetchSummaries();
    }, [fetchSummaries]);

    return {
        summaries,
        isLoading,
        error,
        refresh: fetchSummaries,
    };
}
