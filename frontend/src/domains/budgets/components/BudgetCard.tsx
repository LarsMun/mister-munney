// frontend/src/domains/budgets/components/BudgetCard.tsx

import React, { useState } from 'react';
import type { Budget, CreateBudgetVersion, UpdateBudgetVersion } from '../models/Budget';
import { CategoryStatistics } from '../../categories/models/CategoryStatistics';
import { InlineBudgetEditor } from './InlineBudgetEditor';
import { AddBudgetVersionModal } from './AddBudgetVersionModal';
import ConfirmDialog from '../../../shared/components/ConfirmDialog';
import { formatMoney } from '../../../shared/utils/MoneyFormat';

interface BudgetCardProps {
    budget: Budget;
    categoryStats: CategoryStatistics | null;
    onUpdate: (budgetId: number, updates: any) => Promise<void>;
    onDelete: (budgetId: number) => void;
    onDrop: (budgetId: number, categoryIds: number[]) => void;
    onRemoveCategory: (budgetId: number, categoryId: number) => void;
    onCreateVersion: (budgetId: number, version: CreateBudgetVersion) => Promise<void>;
    onUpdateVersion?: (budgetId: number, versionId: number, version: UpdateBudgetVersion) => Promise<void>;
    onDeleteVersion: (budgetId: number, versionId: number) => Promise<void>;
}

export function BudgetCard({
                               budget,
                               categoryStats,
                               onUpdate,
                               onDelete,
                               onDrop,
                               onRemoveCategory,
                               onCreateVersion,
                               onUpdateVersion,
                               onDeleteVersion
                           }: BudgetCardProps) {
    const [isDragOver, setIsDragOver] = useState(false);
    const [showVersions, setShowVersions] = useState(false);
    const [isAddVersionModalOpen, setIsAddVersionModalOpen] = useState(false);
    const [showDeleteBudgetDialog, setShowDeleteBudgetDialog] = useState(false);
    const [versionToDelete, setVersionToDelete] = useState<number | null>(null);

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

    const handleDeleteVersionClick = (versionId: number) => {
        if (budget.versions.length <= 1) {
            alert('Je kunt de laatste versie niet verwijderen. Een budget moet minimaal één versie hebben.');
            return;
        }
        setVersionToDelete(versionId);
    };

    const handleConfirmDeleteVersion = async () => {
        if (versionToDelete) {
            await onDeleteVersion(budget.id, versionToDelete);
            setVersionToDelete(null);
        }
    };

    const handleCreateVersion = async (version: CreateBudgetVersion) => {
        await onCreateVersion(budget.id, version);
    };

    const sortedVersions = [...budget.versions].sort((a, b) =>
        new Date(b.effectiveFromMonth).getTime() - new Date(a.effectiveFromMonth).getTime()
    );

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
    const currentBudgetAmount = budget.currentMonthlyAmount || 0;
    const difference = currentBudgetAmount - totalExpected;
    const isOverBudget = difference < 0;

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
            {/* Header with Budget Name & Delete Button */}
            <div className="flex justify-between items-start mb-4">
                <div className="flex-1">
                    <InlineBudgetEditor
                        budget={budget}
                        onUpdateBudget={onUpdate}
                        onUpdateVersion={onUpdateVersion}
                        isOverBudget={isOverBudget}
                    />
                </div>
                <button
                    onClick={() => setShowDeleteBudgetDialog(true)}
                    className="ml-2 text-red-600 hover:bg-red-50 hover:text-red-700 px-2 py-1 rounded transition-colors"
                    title="Budget verwijderen"
                >
                    🗑️
                </button>
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

                                        {/* Mediaan laatste 12 maanden - als wit labeltje */}
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

                        {/* Alleen totaal verwacht */}
                        {budget.categories.length > 0 && (
                            <div className="flex justify-between items-center p-2 bg-gray-50 rounded border border-gray-200">
    <span className="text-sm font-medium text-gray-700">
        Verwacht totaal:
    </span>
                                <span className={`text-sm font-bold ${isOverBudget ? 'text-red-600' : 'text-green-600'}`}>
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

            {/* Version Management Section - Now at the bottom */}
            <div className="border-t border-gray-200 pt-4">
                <div className="flex justify-between items-center mb-2">
                    <h4 className="text-sm font-medium text-gray-700">
                        Versies ({budget.versions.length})
                    </h4>
                    <button
                        onClick={() => setIsAddVersionModalOpen(true)}
                        className="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition-colors"
                    >
                        + Nieuwe Versie
                    </button>
                </div>

                {budget.versions.length > 1 && (
                    <button
                        onClick={() => setShowVersions(!showVersions)}
                        className="flex items-center space-x-2 text-sm text-blue-600 hover:text-blue-800 mb-2"
                    >
                        <span>{showVersions ? '▼' : '▶'}</span>
                        <span>Toon versie geschiedenis</span>
                    </button>
                )}

                {/* Version History */}
                {showVersions && budget.versions.length > 1 && (
                    <div className="mt-2 space-y-2 bg-gray-50 rounded-lg p-3 max-h-64 overflow-y-auto">
                        {sortedVersions.map((version) => (
                            <div
                                key={version.id}
                                className={`flex justify-between items-start p-2 rounded ${
                                    version.isCurrent ? 'bg-green-50 border border-green-200' : 'bg-white'
                                }`}
                            >
                                <div className="flex-1">
                                    <div className="flex items-center space-x-2">
                                        <span className="font-medium text-gray-900">
                                            {formatMoney(Math.abs(version.monthlyAmount))}
                                        </span>
                                        {version.isCurrent && (
                                            <span className="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded">
                                                Actief
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-xs text-gray-600 mt-1">
                                        {version.effectiveFromMonth}
                                        {version.effectiveUntilMonth ? ` tot ${version.effectiveUntilMonth}` : ' - open'}
                                    </div>
                                    {version.changeReason && (
                                        <div className="text-xs text-gray-500 mt-1 italic">
                                            "{version.changeReason}"
                                        </div>
                                    )}
                                </div>

                                {/* Delete button - disabled if it's the last version */}
                                <button
                                    onClick={() => handleDeleteVersionClick(version.id)}
                                    disabled={budget.versions.length <= 1}
                                    className={`ml-2 text-xs px-2 py-1 rounded transition-colors ${
                                        budget.versions.length <= 1
                                            ? 'text-gray-300 cursor-not-allowed'
                                            : 'text-red-600 hover:bg-red-50 hover:text-red-700'
                                    }`}
                                    title={budget.versions.length <= 1 ? 'Kan laatste versie niet verwijderen' : 'Verwijder versie'}
                                >
                                    🗑️
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Drag over overlay */}
            {isDragOver && (
                <div className="absolute inset-0 bg-blue-50 bg-opacity-75 flex items-center justify-center rounded-lg">
                    <div className="text-blue-600 font-medium">Laat categorieën hier vallen</div>
                </div>
            )}

            {/* Add Version Modal */}
            <AddBudgetVersionModal
                isOpen={isAddVersionModalOpen}
                onClose={() => setIsAddVersionModalOpen(false)}
                onSubmit={handleCreateVersion}
                currentMonth={budget.currentEffectiveFrom || undefined}
            />

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

            {/* Delete Version Confirm Dialog */}
            <ConfirmDialog
                open={versionToDelete !== null}
                title="Versie verwijderen"
                description="Weet je zeker dat je deze budget versie wilt verwijderen?"
                onConfirm={handleConfirmDeleteVersion}
                onCancel={() => setVersionToDelete(null)}
            />
        </div>
    );
}
