import { useState } from 'react';
import type { AvailableCategory } from '../models/Budget';
import { CategoryStatistics } from '../../categories/models/CategoryStatistics';
import CategoryStatsTooltip from '../../categories/components/CategoryStatsTooltip';
import { formatMoney } from '../../../shared/utils/MoneyFormat';

interface AvailableCategoriesProps {
    categories: AvailableCategory[];
    categoryStats: CategoryStatistics | null;
    onRefresh: () => void;
}

export function AvailableCategories({ categories, categoryStats, onRefresh }: AvailableCategoriesProps) {
    const [selectedCategories, setSelectedCategories] = useState<number[]>([]);
    const [draggedCategories, setDraggedCategories] = useState<number[]>([]);
    const [hoveredCategory, setHoveredCategory] = useState<number | null>(null);
    const [tooltipAnchor, setTooltipAnchor] = useState<HTMLElement | null>(null);

    const unassignedCategories = categories.filter(cat => !cat.isAssigned);

    const getStatsForCategory = (categoryId: number) => {
        return categoryStats?.categories.find(stat => stat.categoryId === categoryId);
    };

    const handleCategorySelect = (categoryId: number) => {
        setSelectedCategories(prev => {
            if (prev.includes(categoryId)) {
                return prev.filter(id => id !== categoryId);
            } else {
                return [...prev, categoryId];
            }
        });
    };

    const handleSelectAll = () => {
        if (selectedCategories.length === unassignedCategories.length) {
            setSelectedCategories([]);
        } else {
            setSelectedCategories(unassignedCategories.map(cat => cat.id));
        }
    };

    const handleDragStart = (e: React.DragEvent, categoryId: number) => {
        const categoriesToDrag = selectedCategories.includes(categoryId)
            ? selectedCategories
            : [categoryId];

        setDraggedCategories(categoriesToDrag);
        e.dataTransfer.setData('text/plain', JSON.stringify(categoriesToDrag));
    };

    const handleDragEnd = () => {
        setDraggedCategories([]);
    };

    const handleMouseEnter = (e: React.MouseEvent<HTMLDivElement>, categoryId: number) => {
        setHoveredCategory(categoryId);
        setTooltipAnchor(e.currentTarget);
    };

    const handleMouseLeave = () => {
        setHoveredCategory(null);
        setTooltipAnchor(null);
    };

    return (
        <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex justify-between items-center mb-4">
                <h2 className="text-lg font-semibold text-gray-900">
                    Beschikbare Categorieën ({unassignedCategories.length})
                </h2>
                <div className="flex space-x-2">
                    {selectedCategories.length > 0 && (
                        <span className="text-sm text-blue-600 font-medium">
                            {selectedCategories.length} geselecteerd
                        </span>
                    )}
                    <button
                        onClick={handleSelectAll}
                        className="text-sm text-blue-600 hover:text-blue-800"
                    >
                        {selectedCategories.length === unassignedCategories.length ? 'Deselecteer alle' : 'Selecteer alle'}
                    </button>
                    <button
                        onClick={onRefresh}
                        className="text-gray-500 hover:text-gray-700"
                        title="Vernieuwen"
                    >
                        🔄
                    </button>
                </div>
            </div>

            {unassignedCategories.length === 0 ? (
                <div className="text-center py-8 text-gray-500">
                    <p>Alle categorieën zijn al toegewezen aan budgets</p>
                </div>
            ) : (
                <>
                    <div className="mb-4 p-3 bg-blue-50 rounded-lg">
                        <p className="text-sm text-blue-800">
                            <strong>Tip:</strong> Selecteer een of meer categorieën en sleep ze naar een budget om toe te wijzen. Hover over een categorie voor uitgaven statistieken.
                        </p>
                    </div>

                    <div className="space-y-2 max-h-96 overflow-y-auto">
                        {unassignedCategories.map((category) => {
                            const stats = getStatsForCategory(category.id);
                            const isHovered = hoveredCategory === category.id;

                            return (
                                <div
                                    key={category.id}
                                    onMouseEnter={(e) => handleMouseEnter(e, category.id)}
                                    onMouseLeave={handleMouseLeave}
                                >
                                    <div
                                        draggable
                                        onDragStart={(e) => handleDragStart(e, category.id)}
                                        onDragEnd={handleDragEnd}
                                        className={`flex items-center space-x-3 p-3 rounded-lg cursor-pointer transition-colors ${
                                            selectedCategories.includes(category.id)
                                                ? 'bg-blue-100 border-blue-300 border-2'
                                                : 'bg-gray-50 hover:bg-gray-100 border border-gray-200'
                                        } ${
                                            draggedCategories.includes(category.id)
                                                ? 'opacity-50'
                                                : ''
                                        }`}
                                        onClick={() => handleCategorySelect(category.id)}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={selectedCategories.includes(category.id)}
                                            onChange={() => handleCategorySelect(category.id)}
                                            className="rounded text-blue-600"
                                        />
                                        <div
                                            className="w-4 h-4 rounded-full flex-shrink-0"
                                            style={{ backgroundColor: category.color || '#6B7280' }}
                                        ></div>
                                        <div className="flex-1">
                                            <div className="font-medium text-gray-900">{category.name}</div>
                                        </div>

                                        {stats && (
                                            <span className="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded">
                                                {formatMoney(stats.averagePerMonth)}/mnd
                                            </span>
                                        )}

                                        <div className="text-gray-400">⋮⋮</div>
                                    </div>

                                    {/* Tooltip via Portal */}
                                    {stats && isHovered && tooltipAnchor && (
                                        <CategoryStatsTooltip
                                            category={stats}
                                            anchorElement={tooltipAnchor}
                                        />
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </>
            )}
        </div>
    );
}