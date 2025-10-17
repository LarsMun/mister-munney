// frontend/src/domains/budgets/hooks/useBudgetSummary.ts

import { useState, useEffect, useCallback } from 'react';
import { getBudgetSummaries } from '../services/BudgetsService';
import type { BudgetSummary, UncategorizedStats } from '../models/BudgetSummary';

export function useBudgetSummary(accountId: number | null, monthYear: string) {
    const [summaries, setSummaries] = useState<BudgetSummary[]>([]);
    const [uncategorized, setUncategorized] = useState<UncategorizedStats>({ totalAmount: 0, count: 0 });
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchSummaries = useCallback(async () => {
        if (!accountId) {
            setSummaries([]);
            setUncategorized({ totalAmount: 0, count: 0 });
            return;
        }

        setIsLoading(true);
        setError(null);

        try {
            const data = await getBudgetSummaries(accountId, monthYear);
            setSummaries(data.summaries || []);
            setUncategorized(data.uncategorized || { totalAmount: 0, count: 0 });
        } catch (err: any) {
            console.error('Error fetching budget summaries:', err);
            setError(err.response?.data?.message || 'Failed to load budget summaries');
            setSummaries([]);
            setUncategorized({ totalAmount: 0, count: 0 });
        } finally {
            setIsLoading(false);
        }
    }, [accountId, monthYear]);

    useEffect(() => {
        fetchSummaries();
    }, [fetchSummaries]);

    return {
        summaries,
        uncategorized,
        isLoading,
        error,
        refresh: fetchSummaries,
    };
}
