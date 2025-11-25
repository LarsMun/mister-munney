import api from '../../../lib/axios';
import { Account, UpdateAccountRequest } from '../models/Account';

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
}

export default new AccountService();
