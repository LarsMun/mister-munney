import { useState } from 'react';
import { X } from 'lucide-react';
import toast from 'react-hot-toast';
import { createTemporaryTransaction } from '../services/TransactionsService';
import SimpleCategoryCombobox from '../../categories/components/SimpleCategoryCombobox';
import type { Category } from '../../categories/models/Category';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    accountId: number;
    onSuccess: () => void;
}

export default function AddTemporaryTransactionModal({ isOpen, onClose, accountId, onSuccess }: Props) {
    const [date, setDate] = useState(new Date().toISOString().split('T')[0]);
    const [description, setDescription] = useState('');
    const [amount, setAmount] = useState('');
    const [transactionType, setTransactionType] = useState<'debit' | 'credit'>('debit');
    const [categoryId, setCategoryId] = useState<number | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    if (!isOpen) return null;

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!description.trim()) {
            toast.error('Omschrijving is verplicht');
            return;
        }

        const parsedAmount = parseFloat(amount);
        if (isNaN(parsedAmount) || parsedAmount <= 0) {
            toast.error('Voer een geldig bedrag in');
            return;
        }

        setIsLoading(true);
        try {
            await createTemporaryTransaction(accountId, {
                date,
                description: description.trim(),
                amount: parsedAmount,
                transactionType,
                categoryId: categoryId ?? undefined,
            });
            toast.success('Tijdelijke transactie aangemaakt');
            onSuccess();
            handleClose();
        } catch (error: any) {
            const message = error?.response?.data?.detail || error?.response?.data?.message || 'Fout bij aanmaken transactie';
            toast.error(message);
        } finally {
            setIsLoading(false);
        }
    };

    const handleClose = () => {
        setDate(new Date().toISOString().split('T')[0]);
        setDescription('');
        setAmount('');
        setTransactionType('debit');
        setCategoryId(null);
        onClose();
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-md">
                <div className="flex items-center justify-between p-6 border-b">
                    <h2 className="text-lg font-semibold text-gray-900">Tijdelijke transactie</h2>
                    <button onClick={handleClose} className="text-gray-400 hover:text-gray-600 transition-colors">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Datum</label>
                        <input
                            type="date"
                            value={date}
                            onChange={e => setDate(e.target.value)}
                            className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                            required
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Omschrijving</label>
                        <input
                            type="text"
                            value={description}
                            onChange={e => setDescription(e.target.value)}
                            placeholder="Bijv. Boodschappen Albert Heijn"
                            className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                            required
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Bedrag</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            value={amount}
                            onChange={e => setAmount(e.target.value)}
                            placeholder="0.00"
                            className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                            required
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => setTransactionType('debit')}
                                className={`flex-1 py-2 text-sm font-medium rounded-md border transition-colors ${
                                    transactionType === 'debit'
                                        ? 'bg-red-50 border-red-300 text-red-700'
                                        : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                                }`}
                            >
                                Af
                            </button>
                            <button
                                type="button"
                                onClick={() => setTransactionType('credit')}
                                className={`flex-1 py-2 text-sm font-medium rounded-md border transition-colors ${
                                    transactionType === 'credit'
                                        ? 'bg-green-50 border-green-300 text-green-700'
                                        : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                                }`}
                            >
                                Bij
                            </button>
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Categorie (optioneel)</label>
                        <SimpleCategoryCombobox
                            categoryId={categoryId}
                            onChange={(cat: Category | null) => setCategoryId(cat?.id ?? null)}
                            refreshCategories={() => {}}
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            onClick={handleClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                            disabled={isLoading}
                        >
                            Annuleren
                        </button>
                        <button
                            type="submit"
                            disabled={isLoading}
                            className="px-4 py-2 text-sm font-medium text-white bg-amber-500 rounded-md hover:bg-amber-600 transition-colors disabled:bg-gray-400"
                        >
                            {isLoading ? 'Opslaan...' : 'Opslaan'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
