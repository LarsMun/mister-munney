// SavingsAccountService.ts

import { api } from '../../../lib/axios';
import type { SavingsAccount } from '../models/SavingsAccount';
import toast from 'react-hot-toast';

export async function fetchSavingsAccounts(accountId: number): Promise<SavingsAccount[]> {
    const response = await api.get(`/account/${accountId}/savings-accounts`);
    return response.data;
}

export async function createSavingsAccount(
    accountId: number,
    savingsAccount: Partial<SavingsAccount>,
    silent = false
): Promise<SavingsAccount> {
    const response = await api.post(`/account/${accountId}/savings-accounts`, savingsAccount);
    if (!silent) {
        toast.success('Spaarrekening succesvol aangemaakt');
    }
    return response.data;
}

export async function assignSavingsAccountToTransaction(
    accountId: number,
    transactionId: number,
    savingsAccountId: number | null
): Promise<void> {
    await api.patch(`/account/${accountId}/transactions/${transactionId}/assign_savings`, {
        savingsAccountId: savingsAccountId ?? 0
    });
}