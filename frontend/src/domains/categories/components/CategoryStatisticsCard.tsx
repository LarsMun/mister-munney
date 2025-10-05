import { useState } from 'react';
import { CategoryStatistics } from '../models/CategoryStatistics';
import { formatMoney } from '../../../shared/utils/MoneyFormat';

interface CategoryStatisticsCardProps {
    statistics: CategoryStatistics | null;
    isLoading: boolean;
    error: string | null;
    onMonthsChange?: (months: string | number) => void;
}

type SortField = 'categoryName' | 'totalAmount' | 'transactionCount' | 'averagePerMonth' | 'monthsWithExpenses' | 'percentageOfTotal';
type SortDirection = 'asc' | 'desc';

export default function CategoryStatisticsCard({
                                                   statistics,
                                                   isLoading,
                                                   error,
                                                   onMonthsChange
                                               }: CategoryStatisticsCardProps) {
    const [selectedMonths, setSelectedMonths] = useState<string>('all');
    const [isExpanded, setIsExpanded] = useState(false);
    const [sortField, setSortField] = useState<SortField>('totalAmount');
    const [sortDirection, setSortDirection] = useState<SortDirection>('desc');

    const handleMonthsChange = (value: string) => {
        setSelectedMonths(value);
        onMonthsChange?.(value === 'all' ? 'all' : parseInt(value));
    };

    const handleSort = (field: SortField) => {
        if (sortField === field) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortField(field);
            setSortDirection('desc');
        }
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

    // Sort categories
    const sortedCategories = [...statistics.categories].sort((a, b) => {
        let aValue = a[sortField];
        let bValue = b[sortField];

        if (typeof aValue === 'string') {
            aValue = aValue.toLowerCase();
            bValue = (bValue as string).toLowerCase();
        }

        if (sortDirection === 'asc') {
            return aValue > bValue ? 1 : -1;
        } else {
            return aValue < bValue ? 1 : -1;
        }
    });

    const SortIcon = ({ field }: { field: SortField }) => {
        if (sortField !== field) {
            return <span className="text-gray-400">⇅</span>;
        }
        return sortDirection === 'asc' ? <span>↑</span> : <span>↓</span>;
    };

    return (
        <div className="bg-white rounded-lg shadow-md overflow-hidden">
            {/* Collapsed Header */}
            <button
                onClick={() => setIsExpanded(!isExpanded)}
                className="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
            >
                <div className="flex items-center space-x-3">
                    <span className="text-2xl">📊</span>
                    <div className="text-left">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Uitgaven per Categorie
                        </h3>
                        <p className="text-sm text-gray-500">
                            {statistics.categories.length} categorieën • Totaal: {formatMoney(statistics.totalSpent)}
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
                    <div className="flex justify-end pt-4 pb-4">
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

                    {/* Statistics Table */}
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                            <tr>
                                <th
                                    onClick={() => handleSort('categoryName')}
                                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                >
                                    <div className="flex items-center space-x-1">
                                        <span>Categorie</span>
                                        <SortIcon field="categoryName" />
                                    </div>
                                </th>
                                <th
                                    onClick={() => handleSort('totalAmount')}
                                    className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                >
                                    <div className="flex items-center justify-end space-x-1">
                                        <span>Totaal</span>
                                        <SortIcon field="totalAmount" />
                                    </div>
                                </th>
                                <th
                                    onClick={() => handleSort('transactionCount')}
                                    className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                >
                                    <div className="flex items-center justify-end space-x-1">
                                        <span>Transacties</span>
                                        <SortIcon field="transactionCount" />
                                    </div>
                                </th>
                                <th
                                    onClick={() => handleSort('monthsWithExpenses')}
                                    className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                >
                                    <div className="flex items-center justify-end space-x-1">
                                        <span>Actieve maanden</span>
                                        <SortIcon field="monthsWithExpenses" />
                                    </div>
                                </th>
                                <th
                                    onClick={() => handleSort('averagePerMonth')}
                                    className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                >
                                    <div className="flex items-center justify-end space-x-1">
                                        <span>Gem/maand</span>
                                        <SortIcon field="averagePerMonth" />
                                    </div>
                                </th>
                                <th
                                    onClick={() => handleSort('percentageOfTotal')}
                                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                >
                                    <div className="flex items-center space-x-1">
                                        <span>% van totaal</span>
                                        <SortIcon field="percentageOfTotal" />
                                    </div>
                                </th>
                            </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                            {sortedCategories.map((category) => (
                                <tr key={category.categoryId} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <div className="flex items-center space-x-2">
                                            <div
                                                className="w-3 h-3 rounded-full"
                                                style={{ backgroundColor: category.categoryColor }}
                                            ></div>
                                            <span className="text-sm font-medium text-gray-900">
                                                    {category.categoryName}
                                                </span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-right">
                                            <span className="text-sm font-semibold text-gray-900">
                                                {formatMoney(category.totalAmount)}
                                            </span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-right">
                                            <span className="text-sm text-gray-600">
                                                {category.transactionCount}
                                            </span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-right">
                                            <span className="text-sm text-gray-600">
                                                {category.monthsWithExpenses}
                                            </span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-right">
                                            <span className="text-sm font-medium text-gray-900">
                                                {formatMoney(category.averagePerMonth)}
                                            </span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <div className="flex items-center space-x-2">
                                            <div className="flex-1 bg-gray-200 rounded-full h-2 max-w-[100px]">
                                                <div
                                                    className="h-2 rounded-full"
                                                    style={{
                                                        width: `${Math.min(category.percentageOfTotal, 100)}%`,
                                                        backgroundColor: category.categoryColor
                                                    }}
                                                ></div>
                                            </div>
                                            <span className="text-sm text-gray-600 min-w-[45px] text-right">
                                                    {category.percentageOfTotal.toFixed(1)}%
                                                </span>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Summary Footer */}
                    <div className="mt-4 pt-4 border-t border-gray-200 flex justify-between items-center">
                        <span className="text-sm font-medium text-gray-700">Totaal</span>
                        <span className="text-lg font-bold text-gray-900">
                            {formatMoney(statistics.totalSpent)}
                        </span>
                    </div>

                    {/* Info Box */}
                    <div className="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p className="text-xs text-blue-800">
                            <strong>ℹ️ Info:</strong> "Gem/maand" toont het gemiddelde uitgavenbedrag per maand,
                            berekend over alleen de maanden waarin uitgaven zijn gedaan. "Actieve maanden"
                            toont hoeveel maanden er daadwerkelijk uitgaven waren in deze categorie.
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}