import { useState } from 'react';
import { CalendarDays, TrendingDown, TrendingUp, MoreVertical, Eye, EyeOff, Edit2 } from 'lucide-react';
import type { RecurringTransaction, UpdateRecurringTransaction } from '../models/RecurringTransaction';
import { FrequencyBadge } from './FrequencyBadge';
import { ConfidenceIndicator } from './ConfidenceIndicator';

type RecurringTransactionCardProps = {
    transaction: RecurringTransaction;
    onUpdate: (id: number, data: UpdateRecurringTransaction) => Promise<RecurringTransaction | null>;
    onDeactivate: (id: number) => Promise<boolean>;
    onSelect?: (transaction: RecurringTransaction) => void;
};

export function RecurringTransactionCard({
    transaction,
    onUpdate,
    onDeactivate,
    onSelect,
}: RecurringTransactionCardProps) {
    const [showMenu, setShowMenu] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [editName, setEditName] = useState(transaction.displayName);

    const isDebit = transaction.transactionType === 'debit';
    const formattedAmount = new Intl.NumberFormat('nl-NL', {
        style: 'currency',
        currency: 'EUR',
    }).format(transaction.predictedAmount);

    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('nl-NL', {
            day: 'numeric',
            month: 'short',
        });
    };

    const handleSaveName = async () => {
        if (editName.trim() && editName !== transaction.displayName) {
            await onUpdate(transaction.id, { displayName: editName.trim() });
        }
        setIsEditing(false);
    };

    const handleToggleActive = async () => {
        if (transaction.isActive) {
            await onDeactivate(transaction.id);
        } else {
            await onUpdate(transaction.id, { isActive: true });
        }
        setShowMenu(false);
    };

    return (
        <div
            className={`bg-white rounded-lg border p-4 hover:shadow-md transition-shadow ${
                !transaction.isActive ? 'opacity-60' : ''
            }`}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex-1 min-w-0">
                    {/* Header: Name and Amount */}
                    <div className="flex items-start justify-between gap-2 mb-2">
                        <div className="flex-1 min-w-0">
                            {isEditing ? (
                                <input
                                    type="text"
                                    value={editName}
                                    onChange={(e) => setEditName(e.target.value)}
                                    onBlur={handleSaveName}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') handleSaveName();
                                        if (e.key === 'Escape') {
                                            setEditName(transaction.displayName);
                                            setIsEditing(false);
                                        }
                                    }}
                                    className="w-full px-2 py-1 text-base font-semibold border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    autoFocus
                                />
                            ) : (
                                <h3
                                    className="text-base font-semibold text-gray-900 truncate cursor-pointer hover:text-blue-600"
                                    onClick={() => onSelect?.(transaction)}
                                    title={transaction.displayName}
                                >
                                    {transaction.displayName}
                                </h3>
                            )}
                            {transaction.categoryName && (
                                <span
                                    className="inline-flex items-center gap-1 text-xs text-gray-500 mt-0.5"
                                >
                                    <span
                                        className="w-2 h-2 rounded-full"
                                        style={{ backgroundColor: transaction.categoryColor || '#ccc' }}
                                    />
                                    {transaction.categoryName}
                                </span>
                            )}
                        </div>
                        <div className="flex items-center gap-1">
                            {isDebit ? (
                                <TrendingDown className="w-4 h-4 text-red-500" />
                            ) : (
                                <TrendingUp className="w-4 h-4 text-green-500" />
                            )}
                            <span
                                className={`text-base font-semibold ${
                                    isDebit ? 'text-red-600' : 'text-green-600'
                                }`}
                            >
                                {isDebit ? '-' : '+'}{formattedAmount}
                            </span>
                        </div>
                    </div>

                    {/* Info row: Frequency, Next Expected, Confidence */}
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-gray-600">
                        <FrequencyBadge frequency={transaction.frequency} size="sm" />

                        <div className="flex items-center gap-1">
                            <CalendarDays className="w-3.5 h-3.5" />
                            <span>
                                Volgende: {formatDate(transaction.nextExpected)}
                            </span>
                        </div>

                        <div className="flex items-center gap-1">
                            <span className="text-xs text-gray-500">Zekerheid:</span>
                            <ConfidenceIndicator score={transaction.confidenceScore} />
                        </div>

                        <span className="text-xs text-gray-400">
                            {transaction.occurrenceCount}x gevonden
                        </span>
                    </div>
                </div>

                {/* Actions Menu */}
                <div className="relative">
                    <button
                        onClick={() => setShowMenu(!showMenu)}
                        className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                    >
                        <MoreVertical className="w-4 h-4 text-gray-500" />
                    </button>

                    {showMenu && (
                        <>
                            <div
                                className="fixed inset-0 z-10"
                                onClick={() => setShowMenu(false)}
                            />
                            <div className="absolute right-0 top-8 w-40 bg-white rounded-lg shadow-lg border py-1 z-20">
                                <button
                                    onClick={() => {
                                        setIsEditing(true);
                                        setShowMenu(false);
                                    }}
                                    className="w-full px-3 py-2 text-left text-sm hover:bg-gray-100 flex items-center gap-2"
                                >
                                    <Edit2 className="w-4 h-4" />
                                    Naam wijzigen
                                </button>
                                <button
                                    onClick={handleToggleActive}
                                    className="w-full px-3 py-2 text-left text-sm hover:bg-gray-100 flex items-center gap-2"
                                >
                                    {transaction.isActive ? (
                                        <>
                                            <EyeOff className="w-4 h-4" />
                                            Deactiveren
                                        </>
                                    ) : (
                                        <>
                                            <Eye className="w-4 h-4" />
                                            Activeren
                                        </>
                                    )}
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
