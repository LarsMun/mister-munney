import toast from 'react-hot-toast';
import AccountService from './AccountService';
import { Account } from '../models/Account';

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
    } catch (error: any) {
        const message = error.response?.data?.detail || 'Fout bij bijwerken accountnaam';
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
    } catch (error: any) {
        const message = error.response?.data?.detail || 'Fout bij instellen default account';
        toast.error(message);
        throw error;
    }
}
