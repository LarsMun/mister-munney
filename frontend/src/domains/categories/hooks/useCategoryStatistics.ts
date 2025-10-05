import { useState, useEffect } from 'react';
import { getCategoryStatistics } from '../services/CategoryService';
import { CategoryStatistics } from '../models/CategoryStatistics';

export function useCategoryStatistics(accountId: number | null, months: string | number = 'all') {
    const [statistics, setStatistics] = useState<CategoryStatistics | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!accountId) {
            setIsLoading(false);
            return;
        }

        setIsLoading(true);
        setError(null);

        getCategoryStatistics(accountId, months)
            .then(data => {
                setStatistics(data);
                setIsLoading(false);
            })
            .catch(err => {
                setError(err.message || 'Fout bij ophalen categorie statistieken');
                setIsLoading(false);
            });
    }, [accountId, months]);

    return { statistics, isLoading, error };
}