// useSavingsAccounts.ts

import { useEffect, useState } from 'react';
import type { SavingsAccount } from '../models/SavingsAccount';
import { fetchSavingsAccounts, createSavingsAccount } from '../services/SavingsAccountService';

export function useSavingsAccounts(accountId: number) {
    const [savingsAccounts, setSavingsAccounts] = useState<SavingsAccount[]>([]);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!accountId) return;
        setLoading(true);
        fetchSavingsAccounts(accountId)
            .then(setSavingsAccounts)
            .catch(() => setError('Kan spaarrekeningen niet ophalen.'))
            .finally(() => setLoading(false));
    }, [accountId]);

    async function addSavingsAccount(newSavingsAccount: Partial<SavingsAccount>) {
        const created = await createSavingsAccount(accountId, newSavingsAccount);
        setSavingsAccounts((prev) => [...prev, created]);
        return created;
    }

    return {
        savingsAccounts,
        loading,
        error,
        addSavingsAccount,
        setSavingsAccounts, // extra toegevoegd zoals in je combobox gebruikt
    };
}