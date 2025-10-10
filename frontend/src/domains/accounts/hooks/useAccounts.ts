import { useState, useEffect, useCallback } from 'react';
import AccountService from '../services/AccountService';
import { Account } from '../models/Account';
import toast from 'react-hot-toast';

/**
 * Hook voor account management
 */
export function useAccounts() {
    const [accounts, setAccounts] = useState<Account[]>([]);
    const [isLoading, setIsLoading] = useState(true);

    const loadAccounts = useCallback(async () => {
        setIsLoading(true);
        try {
            const data = await AccountService.getAll();
            setAccounts(data);
        } catch (error) {
            toast.error('Fout bij laden accounts');
            console.error('Error loading accounts:', error);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        loadAccounts();
    }, [loadAccounts]);

    return {
        accounts,
        isLoading,
        refreshAccounts: loadAccounts,
    };
}
