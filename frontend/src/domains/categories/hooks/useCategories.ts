// UseCategories.ts

import { useEffect, useState } from 'react';
import type { Category } from '../models/Category';
import { fetchCategories, createCategory } from '../services/CategoryService';

export function useCategories(accountId: number) {
    const [categories, setCategories] = useState<Category[]>([]);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!accountId) return;
        const run = async () => {
            await refreshCategories();
        };
        run();
    }, [accountId]);

    async function refreshCategories() {
        setLoading(true);
        try {
            const result = await fetchCategories(accountId);
            setCategories(result);
        } catch {
            setError('Kan categorieën niet ophalen.');
        } finally {
            setLoading(false);
        }
    }

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