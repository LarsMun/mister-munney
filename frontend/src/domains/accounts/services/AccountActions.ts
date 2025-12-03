import toast from 'react-hot-toast';
import AccountService from './AccountService';
import { Account, AccountType, UpdateAccountRequest } from '../models/Account';

interface ApiError {
    response?: {
        data?: {
            detail?: string;
        };
    };
}

/**
 * Action om een account te updaten
 */
export async function updateAccount(
    accountId: number,
    data: UpdateAccountRequest,
    onSuccess?: (account: Account) => void
): Promise<void> {
    try {
        const updatedAccount = await AccountService.update(accountId, data);
        toast.success('Account bijgewerkt');
        onSuccess?.(updatedAccount);
    } catch (error: unknown) {
        const err = error as ApiError;
        const message = err.response?.data?.detail || 'Fout bij bijwerken account';
        toast.error(message);
        throw error;
    }
}

/**
 * Action om een account naam te updaten
 */
export async function updateAccountName(
    accountId: number,
    newName: string,
    onSuccess?: (account: Account) => void
): Promise<void> {
    try {
        const updatedAccount = await AccountService.updateName(accountId, newName);
        toast.success('Accountnaam bijgewerkt');
        onSuccess?.(updatedAccount);
    } catch (error: unknown) {
        const err = error as ApiError;
        const message = err.response?.data?.detail || 'Fout bij bijwerken accountnaam';
        toast.error(message);
        throw error;
    }
}

/**
 * Action om een account als default in te stellen
 */
export async function setDefaultAccount(
    accountId: number,
    onSuccess?: (account: Account) => void
): Promise<void> {
    try {
        const updatedAccount = await AccountService.setDefault(accountId);
        toast.success('Default account ingesteld');
        onSuccess?.(updatedAccount);
    } catch (error: unknown) {
        const err = error as ApiError;
        const message = err.response?.data?.detail || 'Fout bij instellen default account';
        toast.error(message);
        throw error;
    }
}

/**
 * Action om een account type te wijzigen
 */
export async function updateAccountType(
    accountId: number,
    name: string,
    type: AccountType,
    onSuccess?: (account: Account) => void
): Promise<void> {
    try {
        const updatedAccount = await AccountService.update(accountId, { name, type });
        toast.success('Account type bijgewerkt');
        onSuccess?.(updatedAccount);
    } catch (error: unknown) {
        const err = error as ApiError;
        const message = err.response?.data?.detail || 'Fout bij bijwerken account type';
        toast.error(message);
        throw error;
    }
}
