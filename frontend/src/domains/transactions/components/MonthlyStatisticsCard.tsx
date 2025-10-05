import { useState } from 'react';
import { MonthlyStatistics } from '../models/MonthlyStatistics';
import { formatMoney } from '../../../shared/utils/MoneyFormat';

interface MonthlyStatisticsCardProps {
    statistics: MonthlyStatistics | null;
    isLoading: boolean;
    error: string | null;
    onMonthsChange?: (months: string | number) => void;
}

export default function MonthlyStatisticsCard({
                                                  statistics,
                                                  isLoading,
                                                  error,
                                                  onMonthsChange
                                              }: MonthlyStatisticsCardProps) {
    const [selectedMonths, setSelectedMonths] = useState<string>('all');
    const [showDetails, setShowDetails] = useState(false);
    const [isExpanded, setIsExpanded] = useState(false); // Nieuwe state voor hele card

    const handleMonthsChange = (value: string) => {
        setSelectedMonths(value);
        onMonthsChange?.(value === 'all' ? 'all' : parseInt(value));
    };

    if (isLoading) {
        return (
            <div className="bg-white rounded-lg shadow-md p-4">
                <div className="animate-pulse">
                    <div className="h-4 bg-gray-200 rounded w-1/3"></div>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                <p className="text-red-600 text-sm">{error}</p>
            </div>
        );
    }

    if (!statistics) {
        return null;
    }

    const statisticsData = [
        {
            label: 'Trimmed Mean',
            value: statistics.trimmedMean,
            description: 'Gemiddelde na weglaten hoogste/laagste 20%',
            icon: '✂️',
            color: 'text-blue-600'
        },
        {
            label: 'IQR Mean',
            value: statistics.iqrMean,
            description: 'Gemiddelde na verwijderen statistische outliers',
            icon: '📊',
            color: 'text-green-600'
        },
        {
            label: 'Mediaan',
            value: statistics.median,
            description: 'Middelste waarde van alle maanden',
            icon: '📍',
            color: 'text-purple-600'
        },
        {
            label: 'Gewogen Mediaan',
            value: statistics.weightedMedian,
            description: 'Recente maanden wegen zwaarder',
            icon: '⚖️',
            color: 'text-orange-600'
        },
        {
            label: 'Gemiddelde',
            value: statistics.plainAverage,
            description: 'Simpel gemiddelde van alle maanden',
            icon: '➗',
            color: 'text-gray-600'
        },
    ];

    return (
        <div className="bg-white rounded-lg shadow-md overflow-hidden">
            {/* Collapsed Header - Always Visible */}
            <button
                onClick={() => setIsExpanded(!isExpanded)}
                className="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
            >
                <div className="flex items-center space-x-3">
                    <span className="text-2xl">📈</span>
                    <div className="text-left">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Maandelijkse Uitgaven Statistieken
                        </h3>
                        <p className="text-sm text-gray-500">
                            {statistics.monthCount} maanden • Aanbevolen: {formatMoney(statistics.trimmedMean)}
                        </p>
                    </div>
                </div>
                <div className="flex items-center space-x-2">
                    <span className="text-sm text-gray-500">
                        {isExpanded ? 'Inklappen' : 'Uitklappen'}
                    </span>
                    <svg
                        className={`w-5 h-5 text-gray-500 transition-transform duration-200 ${
                            isExpanded ? 'transform rotate-180' : ''
                        }`}
                        fill="none"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
            </button>

            {/* Expanded Content */}
            {isExpanded && (
                <div className="px-6 pb-6 border-t border-gray-100">
                    {/* Period Selector */}
                    <div className="flex justify-end pt-4 pb-2">
                        <select
                            value={selectedMonths}
                            onChange={(e) => handleMonthsChange(e.target.value)}
                            className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="all">Alle maanden</option>
                            <option value="3">Laatste 3 maanden</option>
                            <option value="6">Laatste 6 maanden</option>
                            <option value="12">Laatste 12 maanden</option>
                        </select>
                    </div>

                    {/* Statistics Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                        {statisticsData.map((stat) => (
                            <div
                                key={stat.label}
                                className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
                            >
                                <div className="flex items-start justify-between mb-2">
                                    <div className="flex items-center space-x-2">
                                        <span className="text-2xl">{stat.icon}</span>
                                        <h4 className="font-medium text-gray-700 text-sm">
                                            {stat.label}
                                        </h4>
                                    </div>
                                </div>
                                <p className={`text-2xl font-bold ${stat.color} mb-1`}>
                                    {formatMoney(stat.value)}
                                </p>
                                <p className="text-xs text-gray-500">
                                    {stat.description}
                                </p>
                            </div>
                        ))}
                    </div>

                    {/* Toggle Details Button */}
                    <button
                        onClick={() => setShowDetails(!showDetails)}
                        className="text-sm text-blue-600 hover:text-blue-700 font-medium"
                    >
                        {showDetails ? '▼ Verberg maandelijkse details' : '▶ Toon maandelijkse details'}
                    </button>

                    {/* Monthly Details */}
                    {showDetails && (
                        <div className="mt-4 border-t border-gray-200 pt-4">
                            <h4 className="text-sm font-semibold text-gray-700 mb-3">
                                Uitgaven per maand
                            </h4>
                            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                {statistics.monthlyTotals.map((monthData) => (
                                    <div
                                        key={monthData.month}
                                        className="bg-gray-50 rounded-lg p-3"
                                    >
                                        <p className="text-xs text-gray-600 mb-1">
                                            {new Date(monthData.month + '-01').toLocaleDateString('nl-NL', {
                                                month: 'short',
                                                year: 'numeric'
                                            })}
                                        </p>
                                        <p className="text-sm font-semibold text-gray-900">
                                            {formatMoney(monthData.total)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Info Box */}
                    <div className="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p className="text-xs text-blue-800">
                            <strong>💡 Tip:</strong> De <strong>Trimmed Mean</strong> en <strong>IQR Mean</strong> zijn
                            het meest geschikt voor budgetplanning omdat ze uitschieters negeren.
                            De <strong>Gewogen Mediaan</strong> geeft meer gewicht aan recente maanden.
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}