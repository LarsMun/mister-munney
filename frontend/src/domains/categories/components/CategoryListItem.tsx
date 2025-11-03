// frontend/src/domains/categories/components/CategoryListItem.tsx

import { useState } from 'react';
import type { Category } from '../models/Category';
import type { CategoryStatistic } from '../models/CategoryStatistics';
import { CategoryEditDialog } from './CategoryEditDialog';
import { CategoryDeleteDialog } from './CategoryDeleteDialog';
import { updateCategory, deleteCategory } from '../services/CategoryService';
import { useAccount } from '../../../app/context/AccountContext';

interface CategoryListItemProps {
    category: Category;
    stats: CategoryStatistic | null;
    onRefresh: () => void;
    onCategoryClick?: (category: Category) => void;
    onMergeClick?: (category: Category) => void;
}

export default function CategoryListItem({ category, stats, onRefresh, onCategoryClick, onMergeClick }: CategoryListItemProps) {
    const { accountId } = useAccount();
    const [isExpanded, setIsExpanded] = useState(false);
    const [iconLoadError, setIconLoadError] = useState(false);
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);

    // Format bedrag
    const formatAmount = (amount: number | undefined) => {
        if (amount === undefined || amount === null) return '€0,00';

        return new Intl.NumberFormat('nl-NL', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    };

    // Bepaal of dit een inkomsten of uitgaven categorie is op basis van gemiddeld bedrag
    const isIncome = (stats?.totalAmount || 0) > 0;

    const handleEdit = () => {
        setIsEditDialogOpen(true);
    };

    const handleSaveEdit = async (categoryId: number, updates: { name: string; color: string; icon: string | null }) => {
        if (!accountId) return;
        await updateCategory(accountId, categoryId, updates);
        onRefresh();
    };

    const handleMerge = () => {
        if (onMergeClick) {
            onMergeClick(category);
        } else {
            alert(`Merge functionaliteit komt in Fase 5`);
        }
    };

    const handleDelete = () => {
        setIsDeleteDialogOpen(true);
    };

    const handleConfirmDelete = async (categoryId: number) => {
        if (!accountId) return;
        await deleteCategory(accountId, categoryId);
        onRefresh();
    };

    return (
        <div className="bg-white rounded-lg shadow hover:shadow-md transition-shadow">
            {/* Main content */}
            <div className="p-4">
                <div className="flex items-start justify-between">
                    {/* Left side: Icon + Name + Stats - Clickable */}
                    <div
                        className="flex items-start gap-4 flex-1 cursor-pointer hover:bg-gray-50 -m-2 p-2 rounded-lg transition-colors"
                        onClick={() => onCategoryClick?.(category)}
                    >
                        {/* Category Icon/Color */}
                        <div
                            className="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0"
                            style={{ backgroundColor: category.color }}
                        >
                            {category.icon && !iconLoadError ? (
                                <img
                                    src={category.icon}
                                    alt=""
                                    className="w-6 h-6"
                                    style={{ filter: 'brightness(0) invert(1)' }}
                                    onError={() => setIconLoadError(true)}
                                />
                            ) : (
                                <span className="text-2xl">📁</span>
                            )}
                        </div>

                        {/* Category Info */}
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-1">
                                <h3 className="text-lg font-semibold text-gray-900 truncate">
                                    {category.name}
                                </h3>
                                {isIncome && (
                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                        Inkomsten
                                    </span>
                                )}
                            </div>

                            {/* Statistics Row */}
                            <div className="flex items-center gap-6 text-sm text-gray-600">
                                <div className="flex items-center gap-1">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span className="font-medium">{stats?.transactionCount || 0}</span>
                                    <span>transacties</span>
                                </div>

                                <div className="flex items-center gap-1">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span className={`font-semibold ${isIncome ? 'text-green-600' : 'text-red-600'}`}>
                                        {formatAmount(stats?.totalAmount)}
                                    </span>
                                </div>

                                {stats && stats.transactionCount > 0 && (
                                    <div className="flex items-center gap-1">
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                        <span>Ø {formatAmount(stats.averagePerTransaction)}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Expand/Collapse button */}
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            setIsExpanded(!isExpanded);
                        }}
                        className="ml-4 text-gray-400 hover:text-gray-600"
                    >
                        <svg
                            className={`w-5 h-5 transition-transform ${isExpanded ? 'rotate-180' : ''}`}
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    {/* Right side: Action buttons */}
                    <div className="flex gap-2 ml-4">
                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                handleEdit();
                            }}
                            className="px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                        >
                            Bewerken
                        </button>
                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                handleMerge();
                            }}
                            className="px-3 py-1.5 text-sm font-medium text-purple-700 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors"
                        >
                            Samenvoegen
                        </button>
                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                handleDelete();
                            }}
                            className="px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"
                        >
                            Verwijderen
                        </button>
                    </div>
                </div>
            </div>

            {/* Expanded details */}
            {isExpanded && stats && (
                <div className="border-t border-gray-200 bg-gray-50 p-4">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <div className="text-gray-600 mb-1">Gemiddeld per maand</div>
                            <div className="font-semibold text-gray-900">
                                {formatAmount(stats.averagePerMonth)}
                            </div>
                        </div>
                        <div>
                            <div className="text-gray-600 mb-1">Mediaan (12 mnd)</div>
                            <div className="font-semibold text-gray-900">
                                {formatAmount(stats.medianLast12Months)}
                            </div>
                        </div>
                        <div>
                            <div className="text-gray-600 mb-1">Deze maand</div>
                            <div className="font-semibold text-gray-900">
                                {formatAmount(stats.currentMonthAmount)}
                            </div>
                        </div>
                        <div>
                            <div className="text-gray-600 mb-1">Trend</div>
                            <div className="flex items-center gap-1">
                                {stats.trend === 'increasing' && (
                                    <>
                                        <svg className="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fillRule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" clipRule="evenodd" />
                                        </svg>
                                        <span className="text-red-600 font-semibold">
                                            +{stats.trendPercentage.toFixed(1)}%
                                        </span>
                                    </>
                                )}
                                {stats.trend === 'decreasing' && (
                                    <>
                                        <svg className="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fillRule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                        </svg>
                                        <span className="text-green-600 font-semibold">
                                            {stats.trendPercentage.toFixed(1)}%
                                        </span>
                                    </>
                                )}
                                {stats.trend === 'stable' && (
                                    <>
                                        <svg className="w-4 h-4 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fillRule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clipRule="evenodd" />
                                        </svg>
                                        <span className="text-gray-600 font-semibold">Stabiel</span>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Percentage of total */}
                    {stats.percentageOfTotal > 0 && (
                        <div className="mt-3 pt-3 border-t border-gray-200">
                            <div className="flex items-center justify-between text-sm mb-1">
                                <span className="text-gray-600">Aandeel van totaal</span>
                                <span className="font-medium text-gray-900">
                                    {stats.percentageOfTotal.toFixed(1)}%
                                </span>
                            </div>
                            <div className="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    className="bg-blue-600 h-2 rounded-full transition-all"
                                    style={{ width: `${Math.min(stats.percentageOfTotal, 100)}%` }}
                                />
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Edit Dialog */}
            <CategoryEditDialog
                isOpen={isEditDialogOpen}
                category={category}
                onClose={() => setIsEditDialogOpen(false)}
                onSave={handleSaveEdit}
            />

            {/* Delete Dialog */}
            {accountId && (
                <CategoryDeleteDialog
                    isOpen={isDeleteDialogOpen}
                    category={category}
                    accountId={accountId}
                    onClose={() => setIsDeleteDialogOpen(false)}
                    onDelete={handleConfirmDelete}
                    onMerge={onMergeClick}
                />
            )}
        </div>
    );
}
