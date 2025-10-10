import { createContext, useContext, useState, useEffect, ReactNode, useCallback } from 'react';
import api from '../../lib/axios';

type Account = {
    id: number;
    name: string;
    accountNumber: string;
    isDefault?: boolean; // zorg dat backend dit meestuurt
};

type AccountContextType = {
    accounts: Account[];
    accountId: number | null;
    setAccountId: (id: number) => void;
    hasAccounts: boolean;
    isLoading: boolean;
    refreshAccounts: () => Promise<void>;
};

const AccountContext = createContext<AccountContextType | undefined>(undefined);

export function AccountProvider({ children }: { children: ReactNode }) {
    const [accounts, setAccounts] = useState<Account[]>([]);
    const [accountId, setAccountId] = useState<number | null>(() => {
        const stored = sessionStorage.getItem('accountId');
        const parsed = stored ? Number(stored) : null;
        return isNaN(parsed) ? null : parsed;
    });
    const [isLoading, setIsLoading] = useState(true);

    const refreshAccounts = useCallback(async () => {
        setIsLoading(true);
        try {
            const res = await api.get('/accounts');
            const data = res.data;
            setAccounts(data);

            // Only set default account if no account is currently selected
            if (!accountId && data.length > 0) {
                const defaultAccount = data.find(a => a.isDefault);
                const newAccountId = defaultAccount?.id ?? data[0]?.id ?? null;
                setAccountId(newAccountId);
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

    return (
        <AccountContext.Provider value={{
            accounts,
            accountId,
            setAccountId,
            hasAccounts,
            isLoading,
            refreshAccounts
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