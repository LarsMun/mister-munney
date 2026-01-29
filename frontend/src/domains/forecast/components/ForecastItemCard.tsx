// frontend/src/domains/forecast/components/ForecastItemCard.tsx

import { useState, memo } from 'react';
import { formatMoney } from '../../../shared/utils/MoneyFormat';
import { API_URL } from '../../../lib/api';
import type { ForecastItem, UpdateForecastItem } from '../models/Forecast';

interface ForecastItemCardProps {
    item: ForecastItem;
    type: 'income' | 'expense';
    onUpdate: (itemId: number, data: UpdateForecastItem) => Promise<void>;
    onRemove: (itemId: number) => Promise<void>;
    onDragStart?: (e: React.DragEvent, item: ForecastItem) => void;
    onDragEnd?: () => void;
    isDragging?: boolean;
}

export const ForecastItemCard = memo(function ForecastItemCard({
    item,
    type,
    onUpdate,
    onRemove,
    onDragStart,
    onDragEnd,
    isDragging = false,
}: ForecastItemCardProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [tempValue, setTempValue] = useState('');

    const remaining = item.expectedAmount - item.actualAmount;
    const progress = item.expectedAmount > 0 ? (item.actualAmount / item.expectedAmount) * 100 : 0;
    const isAdjusted = item.expectedAmount !== item.actualAmount; // You might want to track original expected vs current

    // Determine status based on progress
    const isOverBudget = item.actualAmount > item.expectedAmount && item.expectedAmount > 0;
    const isOnTarget = progress >= 95 && progress <= 105 && item.expectedAmount > 0; // Within 5% of target
    const isComplete = item.actualAmount >= item.expectedAmount && item.expectedAmount > 0;

    // Dynamic color based on progress
    const getProgressColor = () => {
        if (isOverBudget) return 'bg-orange-500'; // Over budget
        if (isOnTarget) return 'bg-green-500'; // On target
        return type === 'expense' ? 'bg-blue-500' : 'bg-emerald-500'; // Normal progress
    };

    const progressColor = getProgressColor();
    const remainingColor = isOverBudget ? 'text-orange-600' : type === 'expense' ? 'text-gray-700' : 'text-emerald-600';

    const handleAdjust = async (newRemainingValue: string) => {
        const parsedRemaining = parseFloat(newRemainingValue);
        if (isNaN(parsedRemaining)) {
            setIsEditing(false);
            return;
        }

        // Calculate new expected amount: actual + remaining
        const newExpectedAmount = Math.max(0, item.actualAmount + parsedRemaining);

        try {
            await onUpdate(item.id, { expectedAmount: newExpectedAmount });
            setIsEditing(false);
        } catch (error) {
            console.error('Error updating forecast item:', error);
            setIsEditing(false);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleAdjust(tempValue);
        } else if (e.key === 'Escape') {
            setIsEditing(false);
        }
    };

    const startEditing = () => {
        setIsEditing(true);
        setTempValue(remaining.toFixed(2));
    };

    return (
        <div
            draggable={!!onDragStart}
            onDragStart={onDragStart ? (e) => onDragStart(e, item) : undefined}
            onDragEnd={onDragEnd}
            className={`bg-white rounded-lg p-4 border border-gray-100 hover:border-gray-200 transition-all ${
                isDragging ? 'opacity-50' : ''
            } ${onDragStart ? 'cursor-grab' : ''}`}
        >
            <div className="flex items-center justify-between mb-2">
                <div className="flex items-center gap-2">
                    {item.icon && (
                        <img
                            src={`${API_URL}/api/icons/${item.icon}`}
                            alt=""
                            className="w-5 h-5 flex-shrink-0"
                        />
                    )}
                    <span className="font-medium text-gray-800">
                        {item.customName || item.name}
                    </span>
                    {isOverBudget && (
                        <span className="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">
                            over budget
                        </span>
                    )}
                    {isOnTarget && !isOverBudget && (
                        <span className="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                            ✓ op doel
                        </span>
                    )}
                </div>
            </div>

            {/* Progress bar */}
            <div className="h-2 bg-gray-100 rounded-full overflow-hidden mb-3">
                <div
                    className={`h-full rounded-full transition-all ${progressColor}`}
                    style={{ width: `${Math.min(progress, 100)}%` }}
                />
            </div>

            <div className="flex items-center justify-between text-sm">
                <span className="text-gray-500">
                    {formatMoney(item.actualAmount)} {type === 'expense' ? 'uitgegeven' : 'ontvangen'}
                </span>

                <div className="flex items-center gap-2">
                    {isEditing ? (
                        <div className="flex items-center gap-1">
                            <span className="text-gray-500">Nog</span>
                            <span className="text-gray-500">€</span>
                            <input
                                type="number"
                                step="0.01"
                                value={tempValue}
                                onChange={(e) => setTempValue(e.target.value)}
                                onKeyDown={handleKeyDown}
                                onBlur={() => handleAdjust(tempValue)}
                                className="w-20 px-2 py-1 text-sm border border-blue-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                autoFocus
                            />
                        </div>
                    ) : (
                        <button
                            onClick={startEditing}
                            className={`${remainingColor} font-medium hover:underline cursor-pointer`}
                        >
                            Nog {formatMoney(remaining)}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
});
