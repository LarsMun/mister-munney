import type {
    GroupedRecurringTransactions,
    RecurringTransaction,
    UpdateRecurringTransaction,
    RecurrenceFrequency,
} from '../models/RecurringTransaction';
import { FREQUENCY_LABELS, FREQUENCY_ORDER } from '../models/RecurringTransaction';
import { RecurringTransactionCard } from './RecurringTransactionCard';

type RecurringTransactionListProps = {
    grouped: GroupedRecurringTransactions;
    onUpdate: (id: number, data: UpdateRecurringTransaction) => Promise<RecurringTransaction | null>;
    onDeactivate: (id: number) => Promise<boolean>;
    onSelect?: (transaction: RecurringTransaction) => void;
    showEmpty?: boolean;
};

export function RecurringTransactionList({
    grouped,
    onUpdate,
    onDeactivate,
    onSelect,
    showEmpty = false,
}: RecurringTransactionListProps) {
    // Filter out empty groups unless showEmpty is true
    const visibleGroups = FREQUENCY_ORDER.filter(
        (freq) => showEmpty || grouped[freq].length > 0
    );

    if (visibleGroups.length === 0) {
        return (
            <div className="text-center py-12 text-gray-500">
                <p>Geen terugkerende transacties gevonden.</p>
                <p className="text-sm mt-1">
                    Klik op "Detecteren" om automatisch patronen te vinden.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-8">
            {visibleGroups.map((frequency) => {
                const items = grouped[frequency];
                if (!showEmpty && items.length === 0) return null;

                // Calculate totals for this frequency group
                const debitTotal = items
                    .filter((t) => t.transactionType === 'debit' && t.isActive)
                    .reduce((sum, t) => sum + t.predictedAmount, 0);
                const creditTotal = items
                    .filter((t) => t.transactionType === 'credit' && t.isActive)
                    .reduce((sum, t) => sum + t.predictedAmount, 0);

                return (
                    <section key={frequency}>
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">
                                    {FREQUENCY_LABELS[frequency as RecurrenceFrequency]}
                                </h2>
                                <p className="text-sm text-gray-500">
                                    {items.length} {items.length === 1 ? 'transactie' : 'transacties'}
                                </p>
                            </div>
                            {items.length > 0 && (
                                <div className="text-right text-sm">
                                    {debitTotal > 0 && (
                                        <span className="text-red-600">
                                            -{new Intl.NumberFormat('nl-NL', {
                                                style: 'currency',
                                                currency: 'EUR',
                                            }).format(debitTotal)}
                                        </span>
                                    )}
                                    {debitTotal > 0 && creditTotal > 0 && ' / '}
                                    {creditTotal > 0 && (
                                        <span className="text-green-600">
                                            +{new Intl.NumberFormat('nl-NL', {
                                                style: 'currency',
                                                currency: 'EUR',
                                            }).format(creditTotal)}
                                        </span>
                                    )}
                                </div>
                            )}
                        </div>

                        {items.length === 0 ? (
                            <div className="text-center py-6 text-gray-400 bg-gray-50 rounded-lg">
                                Geen {FREQUENCY_LABELS[frequency as RecurrenceFrequency].toLowerCase()} transacties
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {items.map((transaction) => (
                                    <RecurringTransactionCard
                                        key={transaction.id}
                                        transaction={transaction}
                                        onUpdate={onUpdate}
                                        onDeactivate={onDeactivate}
                                        onSelect={onSelect}
                                    />
                                ))}
                            </div>
                        )}
                    </section>
                );
            })}
        </div>
    );
}
