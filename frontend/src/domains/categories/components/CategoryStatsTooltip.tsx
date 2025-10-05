import { createPortal } from 'react-dom';
import { CategoryStatistic } from '../models/CategoryStatistics';
import { formatMoney } from '../../../shared/utils/MoneyFormat';

interface CategoryStatsTooltipProps {
    category: CategoryStatistic;
    anchorElement: HTMLElement;
}

export default function CategoryStatsTooltip({ category, anchorElement }: CategoryStatsTooltipProps) {
    // Bereken positie van het anchor element
    const rect = anchorElement.getBoundingClientRect();

    const tooltipStyle = {
        position: 'fixed' as const,
        top: `${rect.top}px`,
        left: `${rect.right + 8}px`,
        zIndex: 9999,
    };

    // Trend emoji en kleur
    const getTrendDisplay = () => {
        if (category.trend === 'increasing') {
            return { emoji: '📈', color: 'text-red-600', label: 'Stijgend' };
        } else if (category.trend === 'decreasing') {
            return { emoji: '📉', color: 'text-green-600', label: 'Dalend' };
        }
        return { emoji: '➡️', color: 'text-gray-600', label: 'Stabiel' };
    };

    const trendDisplay = getTrendDisplay();

    return createPortal(
        <div
            style={tooltipStyle}
            className="w-80 bg-white border border-gray-300 rounded-lg shadow-xl p-4"
        >
            {/* Arrow */}
            <div
                className="absolute right-full top-4 border-8 border-transparent border-r-white"
                style={{ marginRight: '-1px' }}
            ></div>

            <div className="space-y-3">
                {/* Header */}
                <div className="flex items-center space-x-2 pb-2 border-b border-gray-200">
                    <div
                        className="w-3 h-3 rounded-full"
                        style={{ backgroundColor: category.categoryColor }}
                    ></div>
                    <h4 className="font-semibold text-gray-900">{category.categoryName}</h4>
                </div>

                {/* Recente statistieken - NIEUW en prominent */}
                <div className="bg-blue-50 rounded-lg p-3 space-y-2">
                    <div className="flex items-center justify-between">
                        <span className="text-sm font-semibold text-blue-900">Laatste 12 maanden:</span>
                        <div className="flex items-center space-x-2">
                            <span className="text-lg font-bold text-blue-600">
                                {formatMoney(category.medianLast12Months)}
                            </span>
                            <span className="text-xs text-gray-500">/mnd</span>
                        </div>
                    </div>

                    {/* Trend indicator */}
                    <div className="flex items-center justify-between text-xs">
                        <span className="text-gray-600">Trend:</span>
                        <div className="flex items-center space-x-1">
                            <span>{trendDisplay.emoji}</span>
                            <span className={`font-semibold ${trendDisplay.color}`}>
                                {trendDisplay.label}
                            </span>
                            {category.trendPercentage !== 0 && (
                                <span className={trendDisplay.color}>
                                    ({category.trendPercentage > 0 ? '+' : ''}{category.trendPercentage.toFixed(1)}%)
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                {/* Historische statistieken */}
                <div className="space-y-2 text-sm pt-2 border-t border-gray-100">
                    <div className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        Historisch overzicht
                    </div>

                    <div className="flex justify-between">
                        <span className="text-gray-600">Mediaan (alle data):</span>
                        <span className="font-semibold text-gray-700">
                            {formatMoney(category.medianAll)}/mnd
                        </span>
                    </div>

                    <div className="flex justify-between">
                        <span className="text-gray-600">Gemiddelde:</span>
                        <span className="font-semibold text-gray-700">
                            {formatMoney(category.averagePerMonth)}/mnd
                        </span>
                    </div>

                    <div className="flex justify-between">
                        <span className="text-gray-600">Totaal uitgegeven:</span>
                        <span className="text-gray-700">
                            {formatMoney(category.totalAmount)}
                        </span>
                    </div>

                    <div className="flex justify-between">
                        <span className="text-gray-600">Transacties:</span>
                        <span className="text-gray-700">{category.transactionCount}</span>
                    </div>

                    <div className="flex justify-between">
                        <span className="text-gray-600">Actieve maanden:</span>
                        <span className="text-gray-700">{category.monthsWithExpenses}</span>
                    </div>

                    <div className="flex justify-between">
                        <span className="text-gray-600">Gem. per transactie:</span>
                        <span className="text-gray-700">
                            {formatMoney(category.averagePerTransaction)}
                        </span>
                    </div>
                </div>

                {/* Percentage indicator */}
                <div className="pt-2 border-t border-gray-100">
                    <div className="flex justify-between items-center mb-1 text-xs">
                        <span className="text-gray-500">Aandeel van totaal:</span>
                        <span className="font-medium text-gray-700">
                            {category.percentageOfTotal.toFixed(1)}%
                        </span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-1.5">
                        <div
                            className="h-1.5 rounded-full"
                            style={{
                                width: `${Math.min(category.percentageOfTotal, 100)}%`,
                                backgroundColor: category.categoryColor
                            }}
                        ></div>
                    </div>
                </div>
            </div>
        </div>,
        document.body
    );
}