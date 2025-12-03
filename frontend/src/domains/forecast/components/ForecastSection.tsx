// frontend/src/domains/forecast/components/ForecastSection.tsx

import { useState } from 'react';
import { ForecastItemCard } from './ForecastItemCard';
import { formatMoney } from '../../../shared/utils/MoneyFormat';
import type { ForecastItem, UpdateForecastItem, CreateForecastItem } from '../models/Forecast';

interface ForecastSectionProps {
    title: string;
    type: 'INCOME' | 'EXPENSE';
    items: ForecastItem[];
    totalExpected: number;
    totalActual: number;
    onAddItem: (item: CreateForecastItem) => Promise<void>;
    onUpdateItem: (itemId: number, data: UpdateForecastItem) => Promise<void>;
    onRemoveItem: (itemId: number) => Promise<void>;
    onReorderItems: (items: ForecastItem[]) => void;
}

export function ForecastSection({
    title,
    type,
    items,
    totalExpected,
    totalActual,
    onAddItem,
    onUpdateItem,
    onRemoveItem,
    onReorderItems
}: ForecastSectionProps) {
    const [isDragOver, setIsDragOver] = useState(false);
    const [draggedItem, setDraggedItem] = useState<ForecastItem | null>(null);
    const [dragOverIndex, setDragOverIndex] = useState<number | null>(null);

    const isIncome = type === 'INCOME';
    const headerColor = isIncome ? 'text-green-800 bg-green-100' : 'text-red-800 bg-red-100';
    const borderColor = isDragOver ? (isIncome ? 'border-green-400' : 'border-red-400') : 'border-gray-200';

    const handleDragOver = (e: React.DragEvent) => {
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
            setDragOverIndex(null);
        }
    };

    const handleDrop = async (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragOver(false);
        setDragOverIndex(null);

        const data = e.dataTransfer.getData('application/json');
        if (data) {
            try {
                const parsed = JSON.parse(data);

                // New item from available list
                if (parsed.itemType && parsed.itemId) {
                    const newItem: CreateForecastItem = {
                        type,
                        expectedAmount: parsed.historicalMedian || 0,
                        ...(parsed.itemType === 'budget' ? { budgetId: parsed.itemId } : { categoryId: parsed.itemId })
                    };
                    await onAddItem(newItem);
                }
            } catch (error) {
                console.error('Error parsing dropped data:', error);
            }
        }

        // Reorder existing item
        const reorderData = e.dataTransfer.getData('text/plain');
        if (reorderData && dragOverIndex !== null) {
            try {
                const parsed = JSON.parse(reorderData);
                if (parsed.forecastItemId) {
                    const itemToMove = items.find(i => i.id === parsed.forecastItemId);
                    if (itemToMove) {
                        const newItems = [...items];
                        const oldIndex = items.findIndex(i => i.id === parsed.forecastItemId);
                        newItems.splice(oldIndex, 1);
                        newItems.splice(dragOverIndex, 0, itemToMove);
                        onReorderItems(newItems);
                    }
                }
            } catch {
                // Not a reorder operation
            }
        }
    };

    const handleItemDragStart = (e: React.DragEvent, item: ForecastItem) => {
        setDraggedItem(item);
        e.dataTransfer.setData('text/plain', JSON.stringify({ forecastItemId: item.id }));
    };

    const handleItemDragEnd = () => {
        setDraggedItem(null);
    };

    const handleItemDragOver = (e: React.DragEvent, index: number) => {
        e.preventDefault();
        e.stopPropagation();
        setDragOverIndex(index);
    };

    const remaining = totalExpected - totalActual;
    const progress = totalExpected > 0 ? (totalActual / totalExpected) * 100 : 0;

    return (
        <div
            className={`bg-white rounded-lg shadow-md overflow-hidden border-2 transition-colors ${borderColor}`}
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
        >
            {/* Header */}
            <div className={`px-6 py-4 ${headerColor}`}>
                <div className="flex justify-between items-center">
                    <h3 className="text-lg font-semibold flex items-center space-x-2">
                        <span>{isIncome ? '💰' : '💸'}</span>
                        <span>{title}</span>
                    </h3>
                    <div className="text-right">
                        <div className="text-sm">
                            {formatMoney(totalActual)} / {formatMoney(totalExpected)}
                        </div>
                        <div className="text-xs opacity-75">
                            {remaining >= 0
                                ? `Nog ${formatMoney(remaining)} ${isIncome ? 'te ontvangen' : 'verwacht uit te geven'}`
                                : `${formatMoney(Math.abs(remaining))} ${isIncome ? 'extra ontvangen' : 'over budget'}`
                            }
                        </div>
                    </div>
                </div>

                {/* Progress bar */}
                <div className="mt-2 h-2 bg-white/50 rounded-full overflow-hidden">
                    <div
                        className={`h-full transition-all ${
                            progress > 100 ? 'bg-yellow-500' : isIncome ? 'bg-green-600' : 'bg-red-600'
                        }`}
                        style={{ width: `${Math.min(progress, 100)}%` }}
                    />
                </div>
            </div>

            {/* Items List */}
            <div className="p-4">
                {items.length === 0 ? (
                    <div className={`text-center py-8 border-2 border-dashed rounded-lg ${
                        isDragOver
                            ? (isIncome ? 'border-green-400 bg-green-50' : 'border-red-400 bg-red-50')
                            : 'border-gray-300'
                    }`}>
                        <p className="text-gray-500">
                            Sleep budgetten of categorieën hierheen
                        </p>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {items.map((item, index) => (
                            <div
                                key={item.id}
                                onDragOver={(e) => handleItemDragOver(e, index)}
                                className={`${
                                    dragOverIndex === index && draggedItem
                                        ? 'border-t-2 border-blue-500'
                                        : ''
                                }`}
                            >
                                <ForecastItemCard
                                    item={item}
                                    onUpdate={onUpdateItem}
                                    onRemove={onRemoveItem}
                                    onDragStart={handleItemDragStart}
                                    onDragEnd={handleItemDragEnd}
                                    isDragging={draggedItem?.id === item.id}
                                />
                            </div>
                        ))}
                    </div>
                )}

                {/* Drop zone at bottom when dragging */}
                {isDragOver && items.length > 0 && (
                    <div
                        onDragOver={(e) => handleItemDragOver(e, items.length)}
                        className={`mt-2 text-center py-4 border-2 border-dashed rounded-lg ${
                            isIncome ? 'border-green-400 bg-green-50' : 'border-red-400 bg-red-50'
                        } ${dragOverIndex === items.length ? 'border-blue-500 bg-blue-50' : ''}`}
                    >
                        <p className="text-sm text-gray-500">Hier neerzetten</p>
                    </div>
                )}
            </div>
        </div>
    );
}
