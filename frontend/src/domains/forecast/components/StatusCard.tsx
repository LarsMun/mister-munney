// frontend/src/domains/forecast/components/StatusCard.tsx

import { formatMoney } from '../../../shared/utils/MoneyFormat';
import { ChevronDown, ChevronUp } from 'lucide-react';

interface StatusCardProps {
    currentBalance: number;
    expectedEndBalance: number;
    totalActualIncome: number;
    totalAdjustedIncome: number;
    totalActualExpenses: number;
    totalAdjustedExpenses: number;
}

export function StatusCard({
    currentBalance,
    expectedEndBalance,
    totalActualIncome,
    totalAdjustedIncome,
    totalActualExpenses,
    totalAdjustedExpenses,
}: StatusCardProps) {
    const toReceive = totalAdjustedIncome - totalActualIncome;
    const toSpend = totalAdjustedExpenses - totalActualExpenses;

    const getStatusColor = (balance: number) => {
        if (balance > 100) {
            return {
                bg: 'bg-emerald-50',
                border: 'border-emerald-200',
                text: 'text-emerald-700',
                indicator: 'bg-emerald-500',
            };
        }
        if (balance >= 0) {
            return {
                bg: 'bg-amber-50',
                border: 'border-amber-200',
                text: 'text-amber-700',
                indicator: 'bg-amber-500',
            };
        }
        return {
            bg: 'bg-red-50',
            border: 'border-red-200',
            text: 'text-red-700',
            indicator: 'bg-red-500',
        };
    };

    const getStatusText = (balance: number) => {
        if (balance > 100) return 'Je komt uit';
        if (balance >= 0) return 'Het wordt krap';
        return 'Je komt niet uit';
    };

    const getStatusEmoji = (balance: number) => {
        if (balance > 100) return '✓';
        if (balance >= 0) return '⚠';
        return '✗';
    };

    const status = getStatusColor(expectedEndBalance);

    return (
        <div className={`${status.bg} ${status.border} border-2 rounded-2xl p-4 md:p-6 transition-all`}>
            {/* Status header */}
            <div className="flex items-center justify-between mb-6">
                <div className="flex items-center gap-3">
                    <div
                        className={`${status.indicator} w-10 h-10 md:w-12 md:h-12 rounded-full flex items-center justify-center text-white text-xl md:text-2xl font-bold shadow-lg`}
                    >
                        {getStatusEmoji(expectedEndBalance)}
                    </div>
                    <div>
                        <h2 className={`text-xl md:text-2xl font-bold ${status.text}`}>
                            {getStatusText(expectedEndBalance)}
                        </h2>
                    </div>
                </div>
                <div className="text-right">
                    <p className="text-xs text-gray-500 uppercase tracking-wide">Eindsaldo</p>
                    <p className={`text-2xl md:text-3xl font-bold ${status.text}`}>
                        {formatMoney(expectedEndBalance)}
                    </p>
                </div>
            </div>

            {/* Visual Progress Bar */}
            <div className="bg-white rounded-xl p-4 md:p-5 shadow-sm mb-5">
                <div className="flex items-center justify-between mb-3">
                    <div>
                        <p className="text-xs text-gray-400 uppercase tracking-wide">Huidig saldo</p>
                        <p className="text-lg md:text-xl font-bold text-gray-800">
                            {formatMoney(currentBalance)}
                        </p>
                    </div>
                    <div className="text-right">
                        <p className="text-xs text-gray-400 uppercase tracking-wide">Eind maand</p>
                        <p className={`text-lg md:text-xl font-bold ${status.text}`}>
                            {formatMoney(expectedEndBalance)}
                        </p>
                    </div>
                </div>

                {/* Main progress bar */}
                <div className="relative h-4 bg-gray-100 rounded-full overflow-hidden">
                    {/* Expected end position marker */}
                    <div
                        className={`absolute top-0 bottom-0 left-0 rounded-full transition-all duration-500 ${
                            expectedEndBalance > 100
                                ? 'bg-emerald-400'
                                : expectedEndBalance >= 0
                                  ? 'bg-amber-400'
                                  : 'bg-red-400'
                        }`}
                        style={{
                            width: `${Math.max(0, Math.min(100, (expectedEndBalance / Math.max(currentBalance, 1)) * 100))}%`,
                        }}
                    />
                    {/* Current position indicator */}
                    <div className="absolute top-0 bottom-0 left-0 w-1 bg-gray-800 rounded-full" />
                </div>

                {/* Scale labels */}
                <div className="flex justify-between mt-2 text-xs text-gray-400">
                    <span>€ 0</span>
                    <span>{formatMoney(currentBalance)}</span>
                </div>
            </div>

            {/* Big visual indicators for income and expenses */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* Nog te ontvangen */}
                <div className="bg-white rounded-xl p-4 md:p-5 shadow-sm border-l-4 border-emerald-500">
                    <div className="flex items-start justify-between">
                        <div>
                            <p className="text-sm text-gray-500 mb-1">Nog te ontvangen</p>
                            <p className="text-2xl md:text-3xl font-bold text-emerald-600">
                                {formatMoney(toReceive)}
                            </p>
                        </div>
                        <div className="w-12 h-12 md:w-14 md:h-14 bg-emerald-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <ChevronDown className="w-6 h-6 md:w-7 md:h-7 text-emerald-600" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="h-2 bg-emerald-100 rounded-full overflow-hidden">
                            <div
                                className="h-full bg-emerald-500 rounded-full transition-all"
                                style={{
                                    width: `${totalAdjustedIncome > 0 ? (totalActualIncome / totalAdjustedIncome) * 100 : 0}%`,
                                }}
                            />
                        </div>
                        <p className="text-xs text-gray-400 mt-1">
                            {formatMoney(totalActualIncome)} van {formatMoney(totalAdjustedIncome)} ontvangen
                        </p>
                    </div>
                </div>

                {/* Nog uit te geven */}
                <div className="bg-white rounded-xl p-4 md:p-5 shadow-sm border-l-4 border-rose-500">
                    <div className="flex items-start justify-between">
                        <div>
                            <p className="text-sm text-gray-500 mb-1">Nog uit te geven</p>
                            <p className="text-2xl md:text-3xl font-bold text-rose-600">
                                {formatMoney(toSpend)}
                            </p>
                        </div>
                        <div className="w-12 h-12 md:w-14 md:h-14 bg-rose-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <ChevronUp className="w-6 h-6 md:w-7 md:h-7 text-rose-600" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="h-2 bg-rose-100 rounded-full overflow-hidden">
                            <div
                                className="h-full bg-rose-500 rounded-full transition-all"
                                style={{
                                    width: `${totalAdjustedExpenses > 0 ? (totalActualExpenses / totalAdjustedExpenses) * 100 : 0}%`,
                                }}
                            />
                        </div>
                        <p className="text-xs text-gray-400 mt-1">
                            {formatMoney(totalActualExpenses)} van {formatMoney(totalAdjustedExpenses)} uitgegeven
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
