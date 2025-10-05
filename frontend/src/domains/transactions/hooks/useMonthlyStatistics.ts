import { useState, useEffect } from 'react';
import { getMonthlyStatistics } from '../services/TransactionsService';
import { MonthlyStatistics } from '../models/MonthlyStatistics';

export function useMonthlyStatistics(accountId: number | null, months: string | number = 'all') {
    const [statistics, setStatistics] = useState<MonthlyStatistics | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!accountId) {
            setIsLoading(false);
            return;
        }

        setIsLoading(true);
        setError(null);

        getMonthlyStatistics(accountId, months)
            .then(data => {
                setStatistics(data);
                setIsLoading(false);
            })
            .catch(err => {
                setError(err.message || 'Fout bij ophalen statistieken');
                setIsLoading(false);
            });
    }, [accountId, months]);

    return { statistics, isLoading, error };
}