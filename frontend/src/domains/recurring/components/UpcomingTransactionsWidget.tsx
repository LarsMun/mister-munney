import { CalendarDays, TrendingDown, TrendingUp, ChevronRight } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useUpcomingTransactions } from '../hooks/useUpcomingTransactions';

type UpcomingTransactionsWidgetProps = {
    maxItems?: number;
};

function getDaysUntilEndOfMonth(): number {
    const now = new Date();
    const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    const diffTime = endOfMonth.getTime() - now.getTime();
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

function getCurrentMonthName(): string {
    return new Date().toLocaleDateString('nl-NL', { month: 'long' });
}

export function UpcomingTransactionsWidget({
    maxItems = 5,
}: UpcomingTransactionsWidgetProps) {
    const daysUntilEndOfMonth = getDaysUntilEndOfMonth();
    const { upcoming, isLoading, totalDebit, totalCredit } = useUpcomingTransactions(daysUntilEndOfMonth);

    if (isLoading) {
        return (
            <div className="bg-white rounded-xl border p-4 animate-pulse">
                <div className="h-5 bg-gray-200 rounded w-1/3 mb-4" />
                <div className="space-y-3">
                    {[1, 2, 3].map((i) => (
                        <div key={i} className="h-12 bg-gray-100 rounded" />
                    ))}
                </div>
            </div>
        );
    }

    const displayItems = upcoming.slice(0, maxItems);
    const hasMore = upcoming.length > maxItems;

    const formatDate = (dateStr: string) => {
        const date = new Date(dateStr);
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);

        if (date.toDateString() === today.toDateString()) {
            return 'Vandaag';
        }
        if (date.toDateString() === tomorrow.toDateString()) {
            return 'Morgen';
        }
        return date.toLocaleDateString('nl-NL', { weekday: 'short', day: 'numeric', month: 'short' });
    };

    return (
        <div className="bg-white rounded-xl border shadow-sm">
            <div className="p-4 border-b">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <CalendarDays className="w-5 h-5 text-blue-600" />
                        <h3 className="font-semibold text-gray-900">Verwacht in {getCurrentMonthName()}</h3>
                    </div>
                    <Link
                        to="/recurring"
                        className="text-sm text-blue-600 hover:text-blue-700 flex items-center gap-1"
                    >
                        Bekijk alles
                        <ChevronRight className="w-4 h-4" />
                    </Link>
                </div>
            </div>

            <div className="p-4">
                {displayItems.length === 0 ? (
                    <p className="text-center text-gray-500 py-4">
                        Geen verwachte transacties deze maand
                    </p>
                ) : (
                    <div className="space-y-3">
                        {displayItems.map((item) => {
                            const isDebit = item.transactionType === 'debit';
                            return (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between py-2"
                                >
                                    <div className="flex items-center gap-3 min-w-0">
                                        <div
                                            className="w-2 h-2 rounded-full flex-shrink-0"
                                            style={{
                                                backgroundColor: item.categoryColor || '#9CA3AF',
                                            }}
                                        />
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium text-gray-900 truncate">
                                                {item.displayName}
                                            </p>
                                            <p className="text-xs text-gray-500">
                                                {formatDate(item.expectedDate)}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-1 flex-shrink-0">
                                        {isDebit ? (
                                            <TrendingDown className="w-3.5 h-3.5 text-red-500" />
                                        ) : (
                                            <TrendingUp className="w-3.5 h-3.5 text-green-500" />
                                        )}
                                        <span
                                            className={`text-sm font-medium ${
                                                isDebit ? 'text-red-600' : 'text-green-600'
                                            }`}
                                        >
                                            {isDebit ? '-' : '+'}
                                            {new Intl.NumberFormat('nl-NL', {
                                                style: 'currency',
                                                currency: 'EUR',
                                            }).format(item.predictedAmount)}
                                        </span>
                                    </div>
                                </div>
                            );
                        })}

                        {hasMore && (
                            <Link
                                to="/recurring"
                                className="block text-center text-sm text-blue-600 hover:text-blue-700 pt-2"
                            >
                                + {upcoming.length - maxItems} meer
                            </Link>
                        )}
                    </div>
                )}
            </div>

            {/* Summary footer */}
            {(totalDebit > 0 || totalCredit > 0) && (
                <div className="px-4 py-3 bg-gray-50 border-t rounded-b-xl">
                    <div className="flex justify-between text-sm">
                        <span className="text-gray-600">Verwacht totaal:</span>
                        <div className="flex gap-3">
                            {totalDebit > 0 && (
                                <span className="text-red-600 font-medium">
                                    -{new Intl.NumberFormat('nl-NL', {
                                        style: 'currency',
                                        currency: 'EUR',
                                    }).format(totalDebit)}
                                </span>
                            )}
                            {totalCredit > 0 && (
                                <span className="text-green-600 font-medium">
                                    +{new Intl.NumberFormat('nl-NL', {
                                        style: 'currency',
                                        currency: 'EUR',
                                    }).format(totalCredit)}
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
