import { useState, useEffect } from 'react';
import { useAccount } from '../../../app/context/AccountContext';
import { updateAccount, setDefaultAccount } from '../services/AccountActions';
import { Account, AccountType } from '../models/Account';

export default function AccountManagement() {
    const { accounts, accountId, isLoading, refreshAccounts } = useAccount();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editValue, setEditValue] = useState('');
    const [localAccounts, setLocalAccounts] = useState(accounts);

    // Sync local state with context
    useEffect(() => {
        setLocalAccounts(accounts);
    }, [accounts]);

    // Get the selected checking account and its savings accounts
    const selectedAccount = localAccounts.find(a => a.id === accountId);
    const childSavingsAccounts = selectedAccount?.linkedSavingsAccounts || [];
    const unlinkedSavingsAccounts = localAccounts.filter(a => a.type === 'SAVINGS' && a.parentAccountId === null);

    // Display: selected account + its children
    const displayAccounts = selectedAccount
        ? [selectedAccount, ...childSavingsAccounts]
        : [];

    const handleStartEdit = (account: Account) => {
        setEditingId(account.id);
        setEditValue(account.name || '');
    };

    const handleCancelEdit = () => {
        setEditingId(null);
        setEditValue('');
    };

    const handleSaveEdit = async (account: Account) => {
        if (!editValue.trim()) {
            return;
        }

        await updateAccount(account.id, { name: editValue.trim(), type: account.type }, (updatedAccount) => {
            setEditingId(null);
            setEditValue('');
            setLocalAccounts(prev =>
                prev.map(acc => acc.id === updatedAccount.id ? updatedAccount : acc)
            );
        });
    };

    const handleSetDefault = async (accountId: number) => {
        await setDefaultAccount(accountId, async () => {
            await refreshAccounts();
        });
    };

    const handleTypeChange = async (account: Account, newType: AccountType) => {
        await updateAccount(account.id, { name: account.name || '', type: newType }, (updatedAccount) => {
            setLocalAccounts(prev =>
                prev.map(acc => acc.id === updatedAccount.id ? updatedAccount : acc)
            );
        });
    };

    const handleLinkSavingsAccount = async (savingsAccount: Account) => {
        if (!accountId) return;

        await updateAccount(savingsAccount.id, {
            name: savingsAccount.name || '',
            type: 'SAVINGS',
            parentAccountId: accountId
        }, (updatedAccount) => {
            setLocalAccounts(prev =>
                prev.map(acc => acc.id === updatedAccount.id ? updatedAccount : acc)
            );
        });
    };

    const handleUnlinkSavingsAccount = async (savingsAccount: Account) => {
        await updateAccount(savingsAccount.id, {
            name: savingsAccount.name || '',
            type: 'SAVINGS',
            parentAccountId: null
        }, (updatedAccount) => {
            setLocalAccounts(prev =>
                prev.map(acc => acc.id === updatedAccount.id ? updatedAccount : acc)
            );
        });
    };

    if (isLoading) {
        return (
            <div className="flex justify-center items-center py-12">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-600">Accounts laden...</p>
                </div>
            </div>
        );
    }

    if (!selectedAccount) {
        return (
            <div className="max-w-4xl mx-auto">
                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p className="text-yellow-800">Selecteer eerst een account in het menu.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="max-w-4xl mx-auto">
            <div className="mb-6">
                <h1 className="text-3xl font-bold text-gray-800 mb-2">Account Beheer</h1>
                <p className="text-gray-600">
                    Beheer je rekeningen voor <span className="font-semibold">{selectedAccount.name || selectedAccount.accountNumber}</span>
                </p>
            </div>

            {/* Main Account */}
            <div className="bg-white rounded-lg shadow mb-6">
                <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 className="text-lg font-semibold text-gray-800">Betaalrekening</h2>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Default
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Naam
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Rekeningnummer
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acties
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            <tr className={selectedAccount.isDefault ? 'bg-blue-50' : ''}>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <button
                                        onClick={() => handleSetDefault(selectedAccount.id)}
                                        type="button"
                                        className={`
                                            w-6 h-6 rounded-full border-2 flex items-center justify-center cursor-pointer transition-all
                                            ${selectedAccount.isDefault
                                                ? 'bg-blue-600 border-blue-600'
                                                : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50'
                                            }
                                        `}
                                        title={selectedAccount.isDefault ? 'Dit is het default account' : 'Klik om dit account als default in te stellen'}
                                    >
                                        {selectedAccount.isDefault && (
                                            <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                            </svg>
                                        )}
                                    </button>
                                </td>
                                <td className="px-6 py-4">
                                    {editingId === selectedAccount.id ? (
                                        <input
                                            type="text"
                                            value={editValue}
                                            onChange={(e) => setEditValue(e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') handleSaveEdit(selectedAccount);
                                                if (e.key === 'Escape') handleCancelEdit();
                                            }}
                                            className="border border-gray-300 rounded px-2 py-1 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            autoFocus
                                        />
                                    ) : (
                                        <div className="flex items-center gap-2">
                                            <span className="text-gray-900">
                                                {selectedAccount.name || <span className="text-gray-400 italic">Geen naam</span>}
                                            </span>
                                            {selectedAccount.isDefault && (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                    Default
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {selectedAccount.accountNumber}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm">
                                    {editingId === selectedAccount.id ? (
                                        <div className="flex gap-2">
                                            <button
                                                onClick={() => handleSaveEdit(selectedAccount)}
                                                className="text-green-600 hover:text-green-900 font-medium"
                                            >
                                                Opslaan
                                            </button>
                                            <button
                                                onClick={handleCancelEdit}
                                                className="text-gray-600 hover:text-gray-900 font-medium"
                                            >
                                                Annuleren
                                            </button>
                                        </div>
                                    ) : (
                                        <button
                                            onClick={() => handleStartEdit(selectedAccount)}
                                            className="text-blue-600 hover:text-blue-900 font-medium"
                                        >
                                            Naam wijzigen
                                        </button>
                                    )}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Linked Savings Accounts */}
            <div className="bg-white rounded-lg shadow mb-6">
                <div className="px-6 py-4 border-b border-gray-200 bg-green-50">
                    <h2 className="text-lg font-semibold text-gray-800">Spaarrekeningen</h2>
                </div>
                {childSavingsAccounts.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Naam
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Rekeningnummer
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acties
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {childSavingsAccounts.map((account) => (
                                    <tr key={account.id}>
                                        <td className="px-6 py-4">
                                            {editingId === account.id ? (
                                                <input
                                                    type="text"
                                                    value={editValue}
                                                    onChange={(e) => setEditValue(e.target.value)}
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') handleSaveEdit(account);
                                                        if (e.key === 'Escape') handleCancelEdit();
                                                    }}
                                                    className="border border-gray-300 rounded px-2 py-1 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    autoFocus
                                                />
                                            ) : (
                                                <span className="text-gray-900">
                                                    {account.name || <span className="text-gray-400 italic">Geen naam</span>}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {account.accountNumber}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            {editingId === account.id ? (
                                                <div className="flex gap-2">
                                                    <button
                                                        onClick={() => handleSaveEdit(account)}
                                                        className="text-green-600 hover:text-green-900 font-medium"
                                                    >
                                                        Opslaan
                                                    </button>
                                                    <button
                                                        onClick={handleCancelEdit}
                                                        className="text-gray-600 hover:text-gray-900 font-medium"
                                                    >
                                                        Annuleren
                                                    </button>
                                                </div>
                                            ) : (
                                                <div className="flex gap-2">
                                                    <button
                                                        onClick={() => handleStartEdit(account)}
                                                        className="text-blue-600 hover:text-blue-900 font-medium"
                                                    >
                                                        Naam wijzigen
                                                    </button>
                                                    <button
                                                        onClick={() => handleUnlinkSavingsAccount(account)}
                                                        className="text-red-600 hover:text-red-900 font-medium"
                                                    >
                                                        Ontkoppelen
                                                    </button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="text-center py-8 text-gray-500">
                        Geen spaarrekeningen gekoppeld aan deze betaalrekening
                    </div>
                )}
            </div>

            {/* Unlinked Savings Accounts */}
            {unlinkedSavingsAccounts.length > 0 && (
                <div className="bg-white rounded-lg shadow mb-6">
                    <div className="px-6 py-4 border-b border-gray-200 bg-yellow-50">
                        <h2 className="text-lg font-semibold text-gray-800">Niet-gekoppelde spaarrekeningen</h2>
                        <p className="text-sm text-gray-600">Deze spaarrekeningen zijn nog niet gekoppeld aan een betaalrekening</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Naam
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Rekeningnummer
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acties
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {unlinkedSavingsAccounts.map((account) => (
                                    <tr key={account.id}>
                                        <td className="px-6 py-4">
                                            <span className="text-gray-900">
                                                {account.name || <span className="text-gray-400 italic">Geen naam</span>}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {account.accountNumber}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            <button
                                                onClick={() => handleLinkSavingsAccount(account)}
                                                className="text-green-600 hover:text-green-900 font-medium"
                                            >
                                                Koppelen aan {selectedAccount.name || 'deze rekening'}
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            <div className="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div className="flex">
                    <div className="flex-shrink-0">
                        <svg className="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                        </svg>
                    </div>
                    <div className="ml-3">
                        <h3 className="text-sm font-medium text-blue-800">Over gekoppelde rekeningen</h3>
                        <div className="mt-2 text-sm text-blue-700">
                            <p>
                                Spaarrekeningen kunnen worden gekoppeld aan een betaalrekening.
                                Dit helpt je om een overzicht te krijgen van al je rekeningen bij dezelfde bank.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
