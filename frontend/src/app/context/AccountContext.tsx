import { createContext, useContext, useState, useEffect, ReactNode, useCallback } from 'react';
import api from '../../lib/axios';
import type { Account } from '../../domains/accounts/models/Account';

type AccountContextType = {
    accounts: Account[];
    accountId: number | null;
    setAccountId: (id: number) => void;
    hasAccounts: boolean;
    isLoading: boolean;
    refreshAccounts: () => Promise<void>;
    updateAccountInContext: (updatedAccount: Account) => void;
};

const AccountContext = createContext<AccountContextType | undefined>(undefined);

export function AccountProvider({ children }: { children: ReactNode }) {
    const [accounts, setAccounts] = useState<Account[]>([]);
    const [accountId, setAccountId] = useState<number | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const refreshAccounts = useCallback(async () => {
        setIsLoading(true);
        try {
            const res = await api.get('/accounts');
            const data: Account[] = res.data;
            setAccounts(data);

            if (data.length > 0) {
                // Find default account
                const defaultAccount = data.find(a => a.isDefault);
                
                // If there's a default account, always use it
                if (defaultAccount) {
                    setAccountId(defaultAccount.id);
                } else {
                    // No default account exists
                    // Check if current selected account still exists
                    const currentAccountExists = accountId && data.find(a => a.id === accountId);
                    
                    if (!currentAccountExists) {
                        // Fall back to stored or lowest ID
                        const storedId = sessionStorage.getItem('accountId');
                        const storedAccountId = storedId ? Number(storedId) : null;
                        const storedAccount = storedAccountId ? data.find(a => a.id === storedAccountId) : null;
                        
                        const sortedAccounts = [...data].sort((a, b) => a.id - b.id);
                        const newAccountId = storedAccount?.id ?? sortedAccounts[0]?.id ?? null;
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

    // Initial load
    useEffect(() => {
        refreshAccounts();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

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
            updateAccountInContext
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