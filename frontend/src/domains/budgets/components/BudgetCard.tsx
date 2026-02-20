// frontend/src/domains/budgets/components/BudgetCard.tsx

import React, { useState } from 'react';
import type { Budget } from '../models/Budget';
import { CategoryStatistics } from '../../categories/models/CategoryStatistics';
import { InlineBudgetEditor } from './InlineBudgetEditor';
import ConfirmDialog from '../../../shared/components/ConfirmDialog';
import { formatMoney } from '../../../shared/utils/MoneyFormat';
import { API_URL } from '../../../lib/api';

interface BudgetCardProps {
    budget: Budget;
    categoryStats: CategoryStatistics | null;
    onUpdate: (budgetId: number, updates: Partial<Budget>) => Promise<void>;
    onDelete: (budgetId: number) => void;
    onDrop: (budgetId: number, categoryIds: number[]) => void;
    onRemoveCategory: (budgetId: number, categoryId: number) => void;
}

export function BudgetCard({
    budget,
    categoryStats,
    onUpdate,
    onDelete,
    onDrop,
    onRemoveCategory
}: BudgetCardProps) {
    const [isDragOver, setIsDragOver] = useState(false);
    const [showDeleteBudgetDialog, setShowDeleteBudgetDialog] = useState(false);

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragOver(true);
    };

    const handleDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragOver(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();

        const rect = e.currentTarget.getBoundingClientRect();
        const x = e.clientX;
        const y = e.clientY;

        if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
            setIsDragOver(false);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragOver(false);

        const categoryIds = e.dataTransfer.getData('text/plain');
        if (categoryIds) {
            try {
                const ids = JSON.parse(categoryIds);
                onDrop(budget.id, ids);
            } catch (error) {
                console.error('Error parsing dropped category IDs:', error);
            }
        }
    };

    // Helper functie om stats voor een categorie te vinden
    const getStatsForCategory = (categoryId: number) => {
        return categoryStats?.categories.find(stat => stat.categoryId === categoryId);
    };

    // Bereken totaal verwachte uitgaven/inkomsten (mediaan laatste 12 maanden)
    const calculateTotalExpected = () => {
        let total = 0;
        budget.categories.forEach(category => {
            const stats = getStatsForCategory(category.id);
            if (stats) {
                total += stats.medianLast12Months;
            }
        });
        return total;
    };

    const totalExpected = calculateTotalExpected();

    return (
        <div
            className={`bg-white rounded-lg shadow-md p-6 transition-all duration-200 relative ${
                isDragOver ? 'border-2 border-blue-500 shadow-lg bg-blue-50' : 'border border-gray-200'
            }`}
            onDragOver={handleDragOver}
            onDragEnter={handleDragEnter}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
        >
            {/* Header with Budget Icon, Name & Delete Button */}
            <div className="flex justify-between items-start mb-4">
                <div className="flex items-start space-x-3 flex-1">
                    {budget.icon && (
                        <img
                            src={`${API_URL}/api/icons/${budget.icon}`}
                            alt=""
                            className="w-8 h-8 mt-1 flex-shrink-0"
                        />
                    )}
                    <div className="flex-1">
                        <InlineBudgetEditor
                            budget={budget}
                            onUpdateBudget={onUpdate}
                        />
                    </div>
                </div>
                <div className="flex items-center gap-2 ml-2">
                    <label className="flex items-center gap-1.5 cursor-pointer" title={budget.isActive ? 'Budget is actief' : 'Budget is inactief'}>
                        <span className="text-xs text-gray-500">Actief</span>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={budget.isActive}
                            onClick={() => onUpdate(budget.id, { isActive: !budget.isActive })}
                            className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors ${
                                budget.isActive ? 'bg-blue-600' : 'bg-gray-300'
                            }`}
                        >
                            <span
                                className={`inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform ${
                                    budget.isActive ? 'translate-x-4.5' : 'translate-x-0.5'
                                }`}
                            />
                        </button>
                    </label>
                    <button
                        onClick={() => setShowDeleteBudgetDialog(true)}
                        className="text-red-600 hover:bg-red-50 hover:text-red-700 px-2 py-1 rounded transition-colors"
                        title="Budget verwijderen"
                    >
                        🗑️
                    </button>
                </div>
            </div>

            {/* Budget Type Badge */}
            <div className="mb-4">
                <span className={`inline-block px-3 py-1 rounded-full text-xs font-semibold ${
                    budget.budgetType === 'EXPENSE' ? 'bg-red-100 text-red-800' :
                    budget.budgetType === 'INCOME' ? 'bg-green-100 text-green-800' :
                    'bg-blue-100 text-blue-800'
                }`}>
                    {budget.budgetType === 'EXPENSE' ? 'Uitgaven' :
                     budget.budgetType === 'INCOME' ? 'Inkomsten' : 'Project'}
                </span>
            </div>

            {/* Categories Section */}
            <div className="mb-4">
                <h4 className="text-sm font-medium text-gray-700 mb-2">
                    Categorieën ({budget.categories.length})
                </h4>
                {budget.categories.length > 0 ? (
                    <>
                        <div className="space-y-1 max-h-32 overflow-y-auto mb-3">
                            {budget.categories.map((category) => {
                                const stats = getStatsForCategory(category.id);

                                return (
                                    <div
                                        key={category.id}
                                        className="flex items-center justify-between bg-gray-50 rounded px-2 py-1"
                                    >
                                        <div className="flex items-center space-x-2 flex-1">
                                            <div
                                                className="w-3 h-3 rounded-full flex-shrink-0"
                                                style={{ backgroundColor: category.color || '#6B7280' }}
                                            ></div>
                                            <span className="text-sm text-gray-700">{category.name}</span>
                                        </div>

                                        {/* Mediaan laatste 12 maanden */}
                                        {stats && (
                                            <span className="text-xs bg-white text-gray-700 font-semibold px-2 py-0.5 rounded border border-gray-300 mr-2">
                                                {formatMoney(Math.abs(stats.medianLast12Months))}
                                            </span>
                                        )}

                                        <button
                                            onClick={() => onRemoveCategory(budget.id, category.id)}
                                            className="text-gray-400 hover:text-red-600 text-xs flex-shrink-0"
                                            title="Verwijderen uit budget"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Totaal verwacht (gebaseerd op historische data) */}
                        {budget.categories.length > 0 && totalExpected !== 0 && (
                            <div className="flex justify-between items-center p-2 bg-blue-50 rounded border border-blue-200">
                                <span className="text-sm font-medium text-gray-700">
                                    Verwacht totaal (mediaan):
                                </span>
                                <span className="text-sm font-bold text-blue-700">
                                    {formatMoney(Math.abs(totalExpected))}
                                </span>
                            </div>
                        )}
                    </>
                ) : (
                    <div className="text-sm text-gray-500 text-center py-4 border-2 border-dashed border-gray-300 rounded">
                        Sleep categorieën hierheen om toe te voegen
                    </div>
                )}
            </div>

            {/* Info Message */}
            <div className="text-xs text-gray-500 italic">
                💡 Budgetten zijn containers voor categorieën. Inzichten worden automatisch berekend in het dashboard.
            </div>

            {/* Drag over overlay */}
            {isDragOver && (
                <div className="absolute inset-0 bg-blue-50 bg-opacity-75 flex items-center justify-center rounded-lg">
                    <div className="text-blue-600 font-medium">Laat categorieën hier vallen</div>
                </div>
            )}

            {/* Delete Budget Confirm Dialog */}
            <ConfirmDialog
                open={showDeleteBudgetDialog}
                title="Budget verwijderen"
                description="Weet je zeker dat je dit budget wilt verwijderen? Deze actie kan niet ongedaan gemaakt worden."
                onConfirm={() => {
                    onDelete(budget.id);
                    setShowDeleteBudgetDialog(false);
                }}
                onCancel={() => setShowDeleteBudgetDialog(false)}
            />
        </div>
    );
}
