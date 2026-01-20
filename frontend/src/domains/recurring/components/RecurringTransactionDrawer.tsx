import * as Dialog from '@radix-ui/react-dialog';
import { X, TrendingDown, TrendingUp, CalendarDays, History } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { RecurringTransaction } from '../models/RecurringTransaction';
import { fetchLinkedTransactions, type LinkedTransaction } from '../services/RecurringService';
import { FrequencyBadge } from './FrequencyBadge';
import { ConfidenceIndicator } from './ConfidenceIndicator';
import { useRequiredAccount } from '../../../app/context/AccountContext';

type Props = {
    transaction: RecurringTransaction | null;
    onClose: () => void;
};

export default function RecurringTransactionDrawer({ transaction, onClose }: Props) {
    const accountId = useRequiredAccount();
    const [open, setOpen] = useState(false);
    const [closing, setClosing] = useState(false);
    const [visibleTransaction, setVisibleTransaction] = useState<RecurringTransaction | null>(null);
    const [linkedTransactions, setLinkedTransactions] = useState<LinkedTransaction[]>([]);
    const [isLoadingLinked, setIsLoadingLinked] = useState(false);

    useEffect(() => {
        if (transaction) {
            setVisibleTransaction(transaction);
            setOpen(true);
            setClosing(false);
            loadLinkedTransactions(transaction.id);
        }
    }, [transaction]);

    const loadLinkedTransactions = async (recurringId: number) => {
        setIsLoadingLinked(true);
        try {
            const data = await fetchLinkedTransactions(accountId, recurringId, 20);
            setLinkedTransactions(data);
        } catch (error) {
            console.error('Failed to load linked transactions:', error);
            setLinkedTransactions([]);
        } finally {
            setIsLoadingLinked(false);
        }
    };

    const handleClose = () => {
        setClosing(true);
        setTimeout(() => {
            setOpen(false);
            setVisibleTransaction(null);
            setLinkedTransactions([]);
            onClose();
        }, 300);
    };

    if (!visibleTransaction) return null;

    const isDebit = visibleTransaction.transactionType === 'debit';
    const formattedAmount = new Intl.NumberFormat('nl-NL', {
        style: 'currency',
        currency: 'EUR',
    }).format(visibleTransaction.predictedAmount);

    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('nl-NL', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    };

    return (
        <Dialog.Root open={open} onOpenChange={handleClose}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 bg-black/30" />
                <Dialog.Content
                    className={`fixed right-0 top-0 h-full w-full max-w-md bg-white shadow-xl p-6 overflow-y-auto z-50
                        ${closing ? 'animate-out slide-out-to-right' : 'animate-in slide-in-from-right'} duration-300`}
                >
                    <div className="flex items-center justify-between mb-6 border-b pb-2">
                        <Dialog.Title className="text-xl font-semibold text-gray-800">
                            {visibleTransaction.displayName}
                        </Dialog.Title>
                        <button
                            onClick={handleClose}
                            className="p-1 rounded hover:bg-gray-100 text-gray-500"
                            aria-label="Sluiten"
                        >
                            <X size={20} />
                        </button>
                    </div>

                    {/* Summary */}
                    <div className="bg-gray-50 rounded-lg p-4 mb-6">
                        <div className="flex items-center justify-between mb-3">
                            <div className="flex items-center gap-2">
                                {isDebit ? (
                                    <TrendingDown className="w-5 h-5 text-red-500" />
                                ) : (
                                    <TrendingUp className="w-5 h-5 text-green-500" />
                                )}
                                <span
                                    className={`text-xl font-bold ${
                                        isDebit ? 'text-red-600' : 'text-green-600'
                                    }`}
                                >
                                    {isDebit ? '-' : '+'}{formattedAmount}
                                </span>
                            </div>
                            <FrequencyBadge frequency={visibleTransaction.frequency} />
                        </div>

                        <div className="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span className="text-gray-500">Volgende verwacht</span>
                                <p className="font-medium flex items-center gap-1">
                                    <CalendarDays className="w-4 h-4 text-gray-400" />
                                    {formatDate(visibleTransaction.nextExpected)}
                                </p>
                            </div>
                            <div>
                                <span className="text-gray-500">Laatst gezien</span>
                                <p className="font-medium">
                                    {formatDate(visibleTransaction.lastOccurrence)}
                                </p>
                            </div>
                            <div>
                                <span className="text-gray-500">Zekerheid</span>
                                <p className="font-medium">
                                    <ConfidenceIndicator score={visibleTransaction.confidenceScore} />
                                </p>
                            </div>
                            <div>
                                <span className="text-gray-500">Aantal keer</span>
                                <p className="font-medium">{visibleTransaction.occurrenceCount}x</p>
                            </div>
                        </div>

                        {visibleTransaction.categoryName && (
                            <div className="mt-3 pt-3 border-t border-gray-200">
                                <span className="text-gray-500 text-sm">Categorie</span>
                                <p className="flex items-center gap-2 font-medium">
                                    <span
                                        className="w-3 h-3 rounded-full"
                                        style={{ backgroundColor: visibleTransaction.categoryColor || '#ccc' }}
                                    />
                                    {visibleTransaction.categoryName}
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Linked Transactions */}
                    <div>
                        <h3 className="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <History className="w-4 h-4" />
                            Gekoppelde transacties
                        </h3>

                        {isLoadingLinked ? (
                            <div className="space-y-2">
                                {[1, 2, 3].map((i) => (
                                    <div key={i} className="h-14 bg-gray-100 rounded-lg animate-pulse" />
                                ))}
                            </div>
                        ) : linkedTransactions.length === 0 ? (
                            <p className="text-gray-500 text-sm">
                                Geen gekoppelde transacties gevonden.
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {linkedTransactions.map((tx) => (
                                    <div
                                        key={tx.id}
                                        className="bg-white border rounded-lg p-3 hover:shadow-sm transition-shadow"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate">
                                                    {tx.description}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    {new Date(tx.date).toLocaleDateString('nl-NL', {
                                                        day: 'numeric',
                                                        month: 'long',
                                                        year: 'numeric',
                                                    })}
                                                </p>
                                            </div>
                                            <span
                                                className={`text-sm font-semibold whitespace-nowrap ${
                                                    isDebit ? 'text-red-600' : 'text-green-600'
                                                }`}
                                            >
                                                {isDebit ? '-' : '+'}
                                                {new Intl.NumberFormat('nl-NL', {
                                                    style: 'currency',
                                                    currency: 'EUR',
                                                }).format(Math.abs(tx.amount))}
                                            </span>
                                        </div>
                                        {tx.categoryName && (
                                            <div className="mt-1 flex items-center gap-1">
                                                <span
                                                    className="w-2 h-2 rounded-full"
                                                    style={{ backgroundColor: tx.categoryColor || '#ccc' }}
                                                />
                                                <span className="text-xs text-gray-500">
                                                    {tx.categoryName}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
