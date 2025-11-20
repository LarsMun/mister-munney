// frontend/src/domains/dashboard/components/HistoricalDataDrawer.tsx

import { useEffect } from 'react';
import { X, TrendingUp, Calendar } from 'lucide-react';
import { formatMoney } from '../../../shared/utils/MoneyFormat';

interface MonthlyData {
    month: string;
    total: number;
    transactionCount: number;
}

interface CategoryInfo {
    id: number;
    name: string;
    color: string;
    icon: string;
}

interface HistoricalData {
    category: CategoryInfo;
    history: MonthlyData[];
    totalAmount: number;
    averagePerMonth: number;
    monthCount: number;
}

interface HistoricalDataDrawerProps {
    isOpen: boolean;
    onClose: () => void;
    data: HistoricalData | null;
    isLoading: boolean;
}

export default function HistoricalDataDrawer({
    isOpen,
    onClose,
    data,
    isLoading
}: HistoricalDataDrawerProps) {
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

    // Calculate max value for bar chart scaling
    const maxAmount = data?.history
        ? Math.max(...data.history.map(h => Math.abs(h.total)))
        : 0;

    return (
        <>
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-black/50 z-40 transition-opacity"
                onClick={onClose}
            />

            {/* Drawer */}
            <div
                className={`fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform transition-transform duration-300 ease-in-out ${
                    isOpen ? 'translate-x-0' : 'translate-x-full'
                }`}
            >
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b border-gray-200 bg-gray-50">
                    <div className="flex items-center gap-3">
                        {data && (
                            <>
                                <div
                                    className="w-4 h-4 rounded-full flex-shrink-0"
                                    style={{ backgroundColor: data.category.color }}
                                />
                                <div>
                                    <h2 className="text-xl font-bold text-gray-900">{data.category.name}</h2>
                                    <p className="text-sm text-gray-600">Historische gegevens</p>
                                </div>
                            </>
                        )}
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
                    ) : !data || data.history.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-gray-500">
                            <Calendar className="w-16 h-16 mb-4 text-gray-300" />
                            <p className="text-lg">Geen historische gegevens gevonden</p>
                        </div>
                    ) : (
                        <div className="p-6">
                            {/* Summary Cards */}
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div className="flex items-center gap-2 text-blue-600 mb-1">
                                        <TrendingUp className="w-4 h-4" />
                                        <span className="text-xs font-medium uppercase">Totaal</span>
                                    </div>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {formatMoney(Math.abs(data.totalAmount))}
                                    </p>
                                </div>
                                <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div className="flex items-center gap-2 text-green-600 mb-1">
                                        <Calendar className="w-4 h-4" />
                                        <span className="text-xs font-medium uppercase">Gemiddeld/maand</span>
                                    </div>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {formatMoney(Math.abs(data.averagePerMonth))}
                                    </p>
                                </div>
                                <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                    <div className="flex items-center gap-2 text-purple-600 mb-1">
                                        <Calendar className="w-4 h-4" />
                                        <span className="text-xs font-medium uppercase">Periode</span>
                                    </div>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {data.monthCount} {data.monthCount === 1 ? 'maand' : 'maanden'}
                                    </p>
                                </div>
                            </div>

                            {/* Monthly Data List with Bar Chart */}
                            <div className="space-y-3">
                                <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                    Maandelijkse uitsplitsing
                                </h3>
                                {data.history.map((monthData) => {
                                    const monthDate = new Date(monthData.month + '-01');
                                    const monthName = monthDate.toLocaleDateString('nl-NL', {
                                        month: 'long',
                                        year: 'numeric'
                                    });
                                    const isPositive = monthData.total >= 0;
                                    const barWidth = maxAmount > 0
                                        ? (Math.abs(monthData.total) / maxAmount) * 100
                                        : 0;

                                    return (
                                        <div
                                            key={monthData.month}
                                            className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
                                        >
                                            <div className="flex items-center justify-between mb-2">
                                                <div className="flex-1">
                                                    <p className="font-medium text-gray-900">{monthName}</p>
                                                </div>
                                                <div className="text-right">
                                                    <p
                                                        className={`font-bold text-lg ${
                                                            isPositive ? 'text-green-600' : 'text-red-600'
                                                        }`}
                                                    >
                                                        {isPositive ? '+' : '-'}
                                                        {formatMoney(Math.abs(monthData.total))}
                                                    </p>
                                                </div>
                                            </div>
                                            {/* Bar Chart */}
                                            <div className="relative h-2 bg-gray-100 rounded-full overflow-hidden">
                                                <div
                                                    className={`absolute left-0 top-0 h-full rounded-full transition-all ${
                                                        isPositive ? 'bg-green-500' : 'bg-red-500'
                                                    }`}
                                                    style={{ width: `${barWidth}%` }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
