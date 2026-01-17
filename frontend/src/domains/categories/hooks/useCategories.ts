// UseCategories.ts

import { useEffect, useState, useCallback } from 'react';
import type { Category } from '../models/Category';
import { fetchCategories, createCategory } from '../services/CategoryService';

export function useCategories(accountId: number) {
    const [categories, setCategories] = useState<Category[]>([]);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);

    const refreshCategories = useCallback(async () => {
        setLoading(true);
        try {
            const result = await fetchCategories(accountId);
            setCategories(result);
        } catch {
            setError('Kan categorieën niet ophalen.');
        } finally {
            setLoading(false);
        }
    }, [accountId]);

    useEffect(() => {
        if (!accountId) return;
        refreshCategories();
    }, [accountId, refreshCategories]);

    async function addCategory(newCategory: Partial<Category>) {
        const created = await createCategory(accountId, newCategory);
        setCategories((prev) => [...prev, created]);
        return created;
    }

    return {
        categories,
        loading,
        error,
        setCategories,
        addCategory,
        refreshCategories,
    };
}
