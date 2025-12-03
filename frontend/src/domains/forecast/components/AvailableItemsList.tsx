// frontend/src/domains/forecast/components/AvailableItemsList.tsx

import { useState } from 'react';
import { formatMoney } from '../../../shared/utils/MoneyFormat';
import { API_URL } from '../../../lib/api';
import type { AvailableBudget, AvailableCategory } from '../models/Forecast';

interface AvailableItemsListProps {
    budgets: AvailableBudget[];
    categories: AvailableCategory[];
    onRefresh: () => void;
}

type TabType = 'budgets' | 'categories';

export function AvailableItemsList({ budgets, categories, onRefresh }: AvailableItemsListProps) {
    const [activeTab, setActiveTab] = useState<TabType>('budgets');
    const [draggedItem, setDraggedItem] = useState<{ type: 'budget' | 'category'; id: number } | null>(null);

    const handleDragStart = (e: React.DragEvent, itemType: 'budget' | 'category', itemId: number, median: number) => {
        setDraggedItem({ type: itemType, id: itemId });
        e.dataTransfer.setData('application/json', JSON.stringify({
            itemType,
            itemId,
            historicalMedian: median
        }));
    };

    const handleDragEnd = () => {
        setDraggedItem(null);
    };

    return (
        <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex justify-between items-center mb-4">
                <h2 className="text-lg font-semibold text-gray-900">
                    Beschikbare Items
                </h2>
                <button
                    onClick={onRefresh}
                    className="text-gray-500 hover:text-gray-700"
                    title="Vernieuwen"
                >

                </button>
            </div>

            {/* Tabs */}
            <div className="flex border-b border-gray-200 mb-4">
                <button
                    onClick={() => setActiveTab('budgets')}
                    className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
                        activeTab === 'budgets'
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'
                    }`}
                >
                    Budgetten ({budgets.length})
                </button>
                <button
                    onClick={() => setActiveTab('categories')}
                    className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
                        activeTab === 'categories'
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'
                    }`}
                >
                    Categorieën ({categories.length})
                </button>
            </div>

            <div className="mb-4 p-3 bg-blue-50 rounded-lg">
                <p className="text-sm text-blue-800">
                    <strong>Tip:</strong> Sleep een budget of categorie naar de inkomsten of uitgaven sectie om toe te voegen aan je forecast.
                </p>
            </div>

            {/* Budget List */}
            {activeTab === 'budgets' && (
                <div className="space-y-2 max-h-96 overflow-y-auto">
                    {budgets.length === 0 ? (
                        <div className="text-center py-8 text-gray-500">
                            <p>Alle budgetten zijn al toegevoegd aan de forecast</p>
                        </div>
                    ) : (
                        budgets.map((budget) => (
                            <div
                                key={budget.id}
                                draggable
                                onDragStart={(e) => handleDragStart(e, 'budget', budget.id, budget.historicalMedian)}
                                onDragEnd={handleDragEnd}
                                className={`flex items-center space-x-3 p-3 rounded-lg cursor-grab transition-colors
                                    bg-gray-50 hover:bg-gray-100 border border-gray-200
                                    ${draggedItem?.type === 'budget' && draggedItem?.id === budget.id ? 'opacity-50' : ''}
                                `}
                            >
                                {budget.icon && (
                                    <img
                                        src={`${API_URL}/api/icons/${budget.icon}`}
                                        alt=""
                                        className="w-6 h-6 flex-shrink-0"
                                    />
                                )}
                                <div className="flex-1">
                                    <div className="font-medium text-gray-900">{budget.name}</div>
                                    <div className="text-xs text-gray-500">
                                        {budget.type === 'INCOME' ? 'Inkomsten' : budget.type === 'EXPENSE' ? 'Uitgaven' : 'Project'}
                                    </div>
                                </div>
                                {budget.historicalMedian > 0 && (
                                    <span className="text-xs font-semibold text-purple-600 bg-purple-50 px-2 py-1 rounded">
                                        ~{formatMoney(budget.historicalMedian)}/mnd
                                    </span>
                                )}
                                <div className="text-gray-400">⋮⋮</div>
                            </div>
                        ))
                    )}
                </div>
            )}

            {/* Category List */}
            {activeTab === 'categories' && (
                <div className="space-y-2 max-h-96 overflow-y-auto">
                    {categories.length === 0 ? (
                        <div className="text-center py-8 text-gray-500">
                            <p>Alle categorieën zijn al toegevoegd aan de forecast</p>
                        </div>
                    ) : (
                        categories.map((category) => (
                            <div
                                key={category.id}
                                draggable
                                onDragStart={(e) => handleDragStart(e, 'category', category.id, category.historicalMedian)}
                                onDragEnd={handleDragEnd}
                                className={`flex items-center space-x-3 p-3 rounded-lg cursor-grab transition-colors
                                    bg-gray-50 hover:bg-gray-100 border border-gray-200
                                    ${draggedItem?.type === 'category' && draggedItem?.id === category.id ? 'opacity-50' : ''}
                                `}
                            >
                                {category.icon && (
                                    <img
                                        src={`${API_URL}/api/icons/${category.icon}`}
                                        alt=""
                                        className="w-6 h-6 flex-shrink-0"
                                    />
                                )}
                                <div className="flex-1">
                                    <div className="font-medium text-gray-900">{category.name}</div>
                                    {category.budgetName && (
                                        <div className="text-xs text-gray-500">
                                            Budget: {category.budgetName}
                                        </div>
                                    )}
                                </div>
                                {category.historicalMedian > 0 && (
                                    <span className="text-xs font-semibold text-purple-600 bg-purple-50 px-2 py-1 rounded">
                                        ~{formatMoney(category.historicalMedian)}/mnd
                                    </span>
                                )}
                                <div className="text-gray-400">⋮⋮</div>
                            </div>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}
