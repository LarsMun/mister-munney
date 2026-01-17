/* eslint-disable react-refresh/only-export-components */
import { createContext, useState, useEffect, ReactNode, useCallback, useContext } from 'react';
import api from '../../lib/axios';
import type { Account } from '../../domains/accounts/models/Account';

export type AccountContextType = {
    accounts: Account[];
    accountId: number | null;
    setAccountId: (id: number) => void;
    hasAccounts: boolean;
    isLoading: boolean;
    refreshAccounts: () => Promise<void>;
    updateAccountInContext: (updatedAccount: Account) => void;
    resetAccountState: () => void;
};

export const AccountContext = createContext<AccountContextType | undefined>(undefined);

export function AccountProvider({ children }: { children: ReactNode }) {
    const [accounts, setAccounts] = useState<Account[]>([]);
    const [accountId, setAccountId] = useState<number | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    // Function to reset all account state (called on logout)
    const resetAccountState = useCallback(() => {
        setAccounts([]);
        setAccountId(null);
        sessionStorage.removeItem('accountId');
    }, []);

    const refreshAccounts = useCallback(async () => {
        setIsLoading(true);
        try {
            const res = await api.get('/accounts');
            const data: Account[] = res.data;
            setAccounts(data);

            if (data.length > 0) {
                // Only auto-select an account if none is currently selected
                if (accountId === null) {
                    // Find default account
                    const defaultAccount = data.find(a => a.isDefault);

                    if (defaultAccount) {
                        // Use default account on initial load
                        setAccountId(defaultAccount.id);
                    } else {
                        // No default account - use stored account if it exists in available accounts, otherwise use first
                        const storedId = sessionStorage.getItem('accountId');
                        const storedAccountId = storedId ? Number(storedId) : null;
                        // IMPORTANT: Only use stored account if it exists in the available accounts for this user
                        const storedAccount = storedAccountId ? data.find(a => a.id === storedAccountId) : null;

                        const sortedAccounts = [...data].sort((a, b) => a.id - b.id);
                        const newAccountId = storedAccount?.id ?? sortedAccounts[0]?.id ?? null;
                        setAccountId(newAccountId);
                    }
                } else {
                    // Account is already selected - verify it still exists
                    const currentAccountExists = data.find(a => a.id === accountId);

                    if (!currentAccountExists) {
                        // Current account was deleted - fall back to default or first
                        const defaultAccount = data.find(a => a.isDefault);
                        const sortedAccounts = [...data].sort((a, b) => a.id - b.id);
                        const newAccountId = defaultAccount?.id ?? sortedAccounts[0]?.id ?? null;
                        setAccountId(newAccountId);
                    }
                }
            }
        } catch (error) {
            console.error('Error loading accounts:', error);
            setAccounts([]);
        } finally {
            setIsLoading(false);
        }
    }, [accountId]);

    // Don't automatically fetch on mount - let App.tsx trigger this after auth check
    // Initial load is now handled by App.tsx when user is authenticated

    // Save accountId to sessionStorage when it changes
    useEffect(() => {
        if (accountId !== null) {
            sessionStorage.setItem('accountId', String(accountId));
        } else {
            sessionStorage.removeItem('accountId');
        }
    }, [accountId]);

    const hasAccounts = accounts.length > 0;

    // Update a single account in the context without refetching all
    const updateAccountInContext = useCallback((updatedAccount: Account) => {
        setAccounts(prev =>
            prev.map(acc => acc.id === updatedAccount.id ? updatedAccount : acc)
        );
    }, []);

    return (
        <AccountContext.Provider value={{
            accounts,
            accountId,
            setAccountId,
            hasAccounts,
            isLoading,
            refreshAccounts,
            updateAccountInContext,
            resetAccountState
        }}>
            {children}
        </AccountContext.Provider>
    );
}

export function useAccount() {
    const context = useContext(AccountContext);
    if (!context) throw new Error('useAccount must be used within AccountProvider');
    return context;
}

export function useRequiredAccount(): number {
    const { accountId } = useAccount();
    if (accountId === null) {
        throw new Error('AccountId is vereist maar is null');
    }
    return accountId;
}
