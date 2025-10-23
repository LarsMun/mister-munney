// frontend/src/domains/dashboard/components/TransactionDrawer.tsx

import { useEffect, useState } from 'react';
import { X } from 'lucide-react';
import { formatMoney } from '../../../shared/utils/MoneyFormat';
import type { Transaction } from '../../transactions/models/Transaction';

interface TransactionDrawerProps {
    isOpen: boolean;
    onClose: () => void;
    categoryName: string;
    categoryColor: string;
    monthYear: string;
    transactions: Transaction[];
    isLoading: boolean;
}

export default function TransactionDrawer({
    isOpen,
    onClose,
    categoryName,
    categoryColor,
    monthYear,
    transactions,
    isLoading
}: TransactionDrawerProps) {
    // Prevent body scroll when drawer is open
    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'unset';
        }
        return () => {
            document.body.style.overflow = 'unset';
        };
    }, [isOpen]);

    if (!isOpen) return null;

    const monthName = new Date(monthYear + '-01').toLocaleDateString('nl-NL', {
        month: 'long',
        year: 'numeric'
    });

    return (
        <>
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-black/50 z-40 transition-opacity"
                onClick={onClose}
            />

            {/* Drawer */}
            <div
                className={`fixed top-0 right-0 h-full w-full max-w-2xl bg-white shadow-2xl z-50 transform transition-transform duration-300 ease-in-out ${
                    isOpen ? 'translate-x-0' : 'translate-x-full'
                }`}
            >
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b border-gray-200 bg-gray-50">
                    <div className="flex items-center gap-3">
                        <div
                            className="w-4 h-4 rounded-full flex-shrink-0"
                            style={{ backgroundColor: categoryColor }}
                        />
                        <div>
                            <h2 className="text-xl font-bold text-gray-900">{categoryName}</h2>
                            <p className="text-sm text-gray-600">{monthName}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 hover:bg-gray-200 rounded-full transition-colors"
                        aria-label="Sluit drawer"
                    >
                        <X className="w-6 h-6 text-gray-600" />
                    </button>
                </div>

                {/* Content */}
                <div className="overflow-y-auto h-[calc(100%-88px)]">
                    {isLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                        </div>
                    ) : transactions.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-gray-500">
                            <p className="text-lg">Geen transacties gevonden</p>
                        </div>
                    ) : (
                        <div className="p-6">
                            <div className="mb-4 text-sm text-gray-600">
                                {transactions.length} {transactions.length === 1 ? 'transactie' : 'transacties'}
                            </div>
                            <div className="space-y-2">
                                {transactions.map((transaction) => (
                                    <div
                                        key={transaction.id}
                                        className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1 min-w-0 mr-4">
                                                <p className="font-medium text-gray-900 truncate">
                                                    {transaction.description}
                                                </p>
                                                <p className="text-sm text-gray-500 mt-1">
                                                    {new Date(transaction.date).toLocaleDateString('nl-NL', {
                                                        day: 'numeric',
                                                        month: 'short',
                                                        year: 'numeric'
                                                    })}
                                                </p>
                                                {transaction.notes && (
                                                    <p className="text-xs text-gray-400 mt-1 line-clamp-2">
                                                        {transaction.notes}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="text-right flex-shrink-0">
                                                <p
                                                    className={`font-semibold text-lg ${
                                                        transaction.transactionType === 'credit'
                                                            ? 'text-green-600'
                                                            : 'text-red-600'
                                                    }`}
                                                >
                                                    {transaction.transactionType === 'credit' ? '+' : '-'}
                                                    {formatMoney(Math.abs(transaction.amount))}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
