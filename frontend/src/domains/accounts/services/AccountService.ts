import api from '../../../lib/axios';
import { Account, UpdateAccountRequest } from '../models/Account';

export interface SavingsMonthHistory {
    month: string;
    deposits: number;
    withdrawals: number;
    netChange: number;
    transactionCount: number;
}

export interface SavingsHistoryResponse {
    account: {
        id: number;
        name: string;
        accountNumber: string;
    };
    history: SavingsMonthHistory[];
    totalDeposits: number;
    totalWithdrawals: number;
    currentBalance: number;
    monthCount: number;
}

export interface SavingsTransaction {
    id: number;
    date: string;
    description: string;
    amount: number;
    type: 'CREDIT' | 'DEBIT';
    counterpartyAccount: string | null;
}

export interface SavingsTransactionsResponse {
    month: string;
    transactions: SavingsTransaction[];
    count: number;
}

/**
 * Service voor account-gerelateerde API calls
 */
class AccountService {
    private readonly baseUrl = '/accounts';

    /**
     * Haal alle accounts op
     */
    async getAll(): Promise<Account[]> {
        const response = await api.get<Account[]>(this.baseUrl);
        return response.data;
    }

    /**
     * Haal een specifiek account op
     */
    async getById(id: number): Promise<Account> {
        const response = await api.get<Account>(`${this.baseUrl}/${id}`);
        return response.data;
    }

    /**
     * Update de naam van een account
     */
    async updateName(id: number, name: string): Promise<Account> {
        const request: UpdateAccountRequest = { name };
        const response = await api.put<Account>(`${this.baseUrl}/${id}`, request);
        return response.data;
    }

    /**
     * Update een account (naam en/of type)
     */
    async update(id: number, data: UpdateAccountRequest): Promise<Account> {
        const response = await api.put<Account>(`${this.baseUrl}/${id}`, data);
        return response.data;
    }

    /**
     * Stel een account in als default
     */
    async setDefault(id: number): Promise<Account> {
        const response = await api.put<Account>(`${this.baseUrl}/${id}/default`);
        return response.data;
    }

    /**
     * Haal spaarrekening historie op
     */
    async getSavingsHistory(accountId: number): Promise<SavingsHistoryResponse> {
        const response = await api.get<SavingsHistoryResponse>(`${this.baseUrl}/${accountId}/savings-history`);
        return response.data;
    }

    /**
     * Haal transacties van een spaarrekening voor een specifieke maand op
     */
    async getSavingsTransactions(accountId: number, month: string): Promise<SavingsTransactionsResponse> {
        const response = await api.get<SavingsTransactionsResponse>(`${this.baseUrl}/${accountId}/savings-transactions/${month}`);
        return response.data;
    }
}

export default new AccountService();
